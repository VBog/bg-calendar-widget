<?php
/* 
    Plugin Name: Bg Calendar Widget
    Plugin URI: https://bogaiskov.ru
    Description: Виджет православного календаря ("Азбука веры")
    Version: 2.0
    Author: VBog
    Author URI: https://bogaiskov.ru 
	License:     GPL2
	GitHub Plugin URI: https://github.com/VBog/bg-calendar-widget
*/

/*  Copyright 2023  Vadim Bogaiskov  (email: vadim.bogaiskov@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*****************************************************************************************

	Блок загрузки плагина 
	
******************************************************************************************/

// Запрет прямого запуска скрипта
if ( !defined('ABSPATH') ) {
	die( 'Sorry, you are not allowed to access this page directly.' ); 
}
define('BG_CALENDAR_WIDGET_VERSION', '2.0');

define('BG_CALENDAR_WIDGET_LOG', dirname(__FILE__ ).'/bg_calendar_widget.log');
// Таблица стилей для плагина
function bg_calendar_widget_enqueue_frontend_styles () {
	wp_enqueue_style( "bg_calendar_widget_styles", plugins_url( '/css/styles.css', plugin_basename(__FILE__) ), array() , BG_CALENDAR_WIDGET_VERSION  );
}
add_action( 'wp_enqueue_scripts' , 'bg_calendar_widget_enqueue_frontend_styles' );

/*****************************************************************************************
	
	Виджет отображает в сайдбаре Православный календарь
	
******************************************************************************************/
class bgCalendarWidget extends WP_Widget {
 
	// создание виджета
	function __construct() {
		parent::__construct(
			'bg_calendar_widget', 
			'Православный календарь', // заголовок виджета
			array( 'description' => 'Выводит в сайдбар Православный кендарь с сайта "Азбука веры".' ) // описание
		);
	}
 
	// фронтэнд виджета
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] ); // к заголовку применяем фильтр (необязательно)
 
		echo $args['before_widget'];
 
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];
 
		$calendar = $this->getAzbykaCalendar ( $instance );
		if ($calendar) {
?>
	<div class="widget-item">
		<div class="widget-inner">
			<?php echo $calendar; ?>
		</div>
	</div>
<?php
		}
		echo $args['after_widget'];
	}
 
	// бэкэнд виджета
	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Заголовок</label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			<label><input type="checkbox" id="<?php echo $this->get_field_id( 'icon' ); ?>" name="<?php echo $this->get_field_name( 'icon' ); ?>"<?php echo ( isset($instance['icon']) && $instance['icon'] ) ? ' checked' : '' ?>> Икона </label>
			<span>&nbsp;</span>
			<label><input type="checkbox" id="<?php echo $this->get_field_id( 'readings' ); ?>" name="<?php echo $this->get_field_name( 'readings' ); ?>"<?php echo ( isset($instance['readings']) && $instance['readings'] ) ? ' checked' : '' ?>> Чтения </label>
		</p>
		<?php 
	}
 
	// сохранение настроек виджета
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['icon'] = isset($new_instance['icon']) ? filter_var( $new_instance['icon'], FILTER_VALIDATE_BOOLEAN ) : false;
		$instance['readings'] = isset($new_instance['readings']) ? filter_var( $new_instance['readings'], FILTER_VALIDATE_BOOLEAN ) : false;
		
		delete_transient('getCalendar_key_'.date('Y-m-d'));
		
		return $instance;
	}

	// Формирование текста календаря
	private function getAzbykaCalendar ( $instance ) {

		$weekday = ['Понедельник','Вторник','Среда','Четверг','Пятница','Суббота','<span>Воскресенье</span>'];
		$monthes = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];

		$events = array();
		$main_event = array();
		$prefeast_event = array();
		$special_event = array();
	
		if (function_exists('bg_currentDate')) $date = bg_currentDate();
		else {
			if (isset($_GET["date"])) {		// Задана дата
				$date = $_GET["date"];
			} else {
				$date = date('Y-m-d');
			}
		}
		
		$date = apply_filters('bg_calendar_widget_date', $date);
		
		list($y,$m,$d) = explode ('-', $date);
		$wd = date("N",strtotime($date)); 
			
		$sufix = [
			'holidays' => 'prazdnik-',
			'saints' => 'sv-',
			'ikons' => 'ikona-'
		];

		$the_key='getCalendar_key_'.$date;
		if(false===($quote=get_transient($the_key))) {
			
			$main_feast = array();
			$feasts = array();
			$feast_type = ['Великий', 'Бденный',  'Полиелейный', 'Славословный', 'Шестеричный', 'Вседневный'];
			
			$quote = '<div class="saints">';
			$response = wp_remote_get( 'https://azbyka.ru/days/api/day/'.$date.'/tropary.json' );
			$json = wp_remote_retrieve_body($response);
			if ($json) {
				$data = json_decode($json, false);

			// Соберем все события в единый массив
				// Праздники
				foreach($data->holidays as $holiday) {
					$event = (object)array();
					if (!empty($holiday->full_title)) $event->title = strip_tags($holiday->full_title);
					elseif (!empty($holiday->title)) $event->title = strip_tags($holiday->title);
					else continue;
					if ($holiday->ideograph) $event->ideograph = $holiday->ideograph;
					else $event->ideograph = 6;
					$event->priority = 0;

					$event->id = 'h'. $holiday->id;
					$event->type = 'holidays';
					$event->feast_type = $holiday->in_honor;
					$event->group = 0;
					$event->url = 'https://azbyka.ru/days/prazdnik-'. $holiday->uri;
					$event->imgs = $holiday->imgs;		

					switch ($holiday->subtype) {
						case 'sunday':
							$event->type = 'special';
							$event->ideograph = 8;				
							$special_event = $event;
						break;

						case 'saturday_before':
						case 'sunday_before':
						case 'saturday_after':
						case 'sunday_after':
							$event->ideograph = 8;				
							$event->type = 'weekend';
							$special_event = $event;
						break;

						case 'before':
							$event->type = 'prefeast';
							$event->ideograph = 8;				
							$prefeast_event = $event;
						break;
						case 'after':
							$event->type = 'afterfeast';
							$event->ideograph = 8;				
							$prefeast_event = $event;
						break;
						case 'leave_taking':
							$event->type = 'feastend';
							$event->ideograph = 4;				
							$prefeast_event = $event;
						break;

						default:
							$events[] = $event;
					}
				}

				// Святые
				foreach($data->saints as $saint) {

					$event = (object)array();
					// Название
					if (!empty($saint->title_genitive)) $title = strip_tags($saint->title_genitive);
					elseif (!empty($saint->title)) $title = strip_tags($saint->title);
					else continue;
					
					if ($saint->prefix) $title = strip_tags($saint->prefix).' '.$title;
					elseif ($saint->type_of_sanctity) $title = strip_tags($saint->type_of_sanctity).' '.$title;
					if ($saint->suffix) $title .= ' '.strip_tags($saint->suffix);
					
					// Имя
					$name = strip_tags($saint->name);
					
					// Лик святости
					if ($saint->group > 0) $sanctity = $saint->type_of_sanctity_plural;
					else $sanctity = $saint->type_of_sanctity;
					
					// Собираем святых в группы 
					$key = array_search($saint->group, array_column($events, 'group'));
					if ($saint->group && $key !== false) {
						$union = (!empty($events[$key]->union))?($events[$key]->union):',';
						$events[$key]->title .= $union.' '.$title;
						$events[$key]->name .= $union.' '.$name;
						// Если в группе разные лики святости, то принимаем общее название по первому святому в группе
						if ($events[$key]->sanctity != $sanctity) $events[$key]->sanctity = $types_of_sanctity[$events[$key]->sanctity];
						continue;
					}
					
					// Данные о группе от первого святого в списке
					$event->title = $title;
					$event->name = $name;
					$event->sanctity = $sanctity;
					$event->group = $saint->group;
					$event->gender = $saint->sex;
					
					if ($saint->ideograph) {
						if ($saint->ideograph == 6 && $saint->priority > 1) $event->ideograph = 7;
						if ($saint->ideograph == 7 && $saint->priority == 1) $event->ideograph = 6;
						else $event->ideograph = $saint->ideograph;
					} else $event->ideograph = 7;
					$event->priority = $saint->priority;
					
					$event->id = 's'. $saint->id;
					$event->type = 'saints';
					$event->feast_type = 'saint';
					$event->url = 'https://azbyka.ru/days/sv-'. $saint->uri;
					$event->imgs = $saint->imgs;		
					
					$events[] = $event;
				}

				// Иконы
				foreach($data->ikons as $ikon) {
					$event = (object)array();
					if (!empty($ikon->clean_title)) $event->title = strip_tags($ikon->clean_title);
					elseif (!empty($ikon->title)) $event->title = strip_tags($ikon->title);
					else continue;
					if (!empty($ikon->ideograph)) {
						if ($ikon->ideograph == 6) $ikon->ideograph = 7;
						else $event->ideograph = $ikon->ideograph;
					} else {
						$event->ideograph = 7;
						$event->title = 'иконы Богородицы '.$event->title;
					}
					$event->priority = 8;
					
					$event->id = 'i'. $ikon->id;
					$event->type = 'ikons';
					$event->feast_type = 'our_lady';
					$event->group = 0;
					$event->url = 'https://azbyka.ru/days/ikona-'. $ikon->uri;
					$event->imgs = $ikon->imgs;		

					$events[] = $event;
				}
			}
			// Сортируем события
			usort($events, function($a, $b) {
				return strcmp($a->priority, $b->priority);
			});
			
		// Икона дня
			if (isset($instance['icon']) && $instance['icon'] ) {
				foreach ($events as $event) {
					if (!empty($event->imgs)) {
						$image = '<div class="days-image">';
							$image .= '<a title="'. $event->title .'" href="https://azbyka.ru/days/'. $sufix[$event->type].$event->uri .'" target="_blank" rel="noopener">';
							$image.= '<img src="'.$event->imgs[0]->preview_absolute_url.'" alt="'.$event->title.'">';
							$image .= '</a>';
						$image .= '</div>';
						break;
					}	
				}
			}
		// Дата
			$image .= '<span class="week_day"><a href="https://azbyka.ru/days/'. $date .'"'.(($wd==7)?' style="color:red"':"").'>'. $weekday[$wd-1] .',<br>'. (int) $d .' '. $monthes[$m-1] .' '. $y .'г.</a></span><br>';
			if (!empty($special_event))	$image .= '<span class="round_week">'.$special_event->title.'</span>';	
			else $image .= '<span class="round_week">'.strip_tags($data->fasting->round_week).'</span>';
			if (!empty($prefeast_event)) $image .= '<p class="prefeast">'.strip_tags($prefeast_event->title).'</p>';
			
		// Список праздников и святых
			$priority = 0;
			$paragraph = '';
			$quote = '';
			foreach ($events as $event) {
				if ($event->priority != $priority && $paragraph) {
					$quote .= '<p>'.substr($paragraph, 0, -2).'.</p>'; 
					$paragraph = '';
				}
				$priority = $event->priority; 
				// Знак Типикона
				if ($event->ideograph < 7) $symbol ='<img src="'.plugins_url( 'img/S'.$event->ideograph.'.gif', __FILE__ ).'" title="'.$feast_type[$event->ideograph-1].' праздник" />&nbsp;';
				else $symbol ='';
				// Текст абзаца
				$paragraph .= $symbol.'<span class="feast'.$event->ideograph.'"><a title="'. $event->title .'" href="'. $event->url .'" target="_blank" rel="noopener">'.$event->title.'</a></span>; ';
			}
			if ($paragraph) $quote .= '<p>'.substr($paragraph, 0, -2).'.</p>'; 

		//Чтения дня
			if(isset($instance['readings']) && $instance['readings'] && !empty($data->texts)){
				$quote .= '<hr>'.$data->texts[0]->text.'<label class="btn-info"><input type="checkbox" class="btn-info-checkbox"><span class="btn-info-inner"><b>Чтения Св. Писания на богослужениях</b><br>Зач. - № <a href="https://azbyka.ru/zachala" target="_blank">зачала</a><br>Утр. - на <a href="https://azbyka.ru/utrenya" target="_blank">Утрени</a>.<br>Лит. - на <a href="https://azbyka.ru/liturgiya" target="_blank">Литургии</a>.</span><i class="fa fa-question-circle"></i></label>';
			}
			
			$quote = '<div class="widget-title saints-title"><a href="/days/" title="Православный календарь" target="_blank" rel="noopener">Православный календарь</a></div>'.
					 '<div class="date-today">'.$image.'</div>'.
					 '<div class="saints">'.$quote.'</div>';

			set_transient( $the_key, $quote, 60*MINUTE_IN_SECONDS );
		}
		return $quote;
	}
}
 
/*****************************************************************************************

	Регистрация виджета
	
******************************************************************************************/
function bg_calendar_widget_load() {
	register_widget( 'bgCalendarWidget' );
}
add_action( 'widgets_init', 'bg_calendar_widget_load' );

