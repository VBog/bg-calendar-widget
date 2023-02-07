<?php
/* 
    Plugin Name: Bg Calendar Widget
    Plugin URI: https://bogaiskov.ru
    Description: Виджет православного календаря ("Азбука веры")
    Version: 2.3.1
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
define('BG_CALENDAR_WIDGET_VERSION', '2.3.1');

define('BG_CALENDAR_WIDGET_DEBUG', false);

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
			<span>&nbsp;</span>
			<label><input type="checkbox" id="<?php echo $this->get_field_id( 'links' ); ?>" name="<?php echo $this->get_field_name( 'links' ); ?>"<?php echo ( isset($instance['links']) && $instance['links'] ) ? ' checked' : '' ?>> Ссылки </label>
		</p>
		<?php 
	}
 
	// сохранение настроек виджета
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['icon'] = isset($new_instance['icon']) ? filter_var( $new_instance['icon'], FILTER_VALIDATE_BOOLEAN ) : false;
		$instance['readings'] = isset($new_instance['readings']) ? filter_var( $new_instance['readings'], FILTER_VALIDATE_BOOLEAN ) : false;
		$instance['links'] = isset($new_instance['links']) ? filter_var( $new_instance['links'], FILTER_VALIDATE_BOOLEAN ) : false;
		
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
		$dd = ($y-$y%100)/100 - ($y-$y%400)/400 - 2;
		$old = date("Y-m-d",strtotime ($date.' - '.$dd.' days')) ;
		list($old_y,$old_m,$old_d) = explode ('-', $old);
		
			
		$sufix = [
			'holidays' => 'prazdnik-',
			'saints' => 'sv-',
			'ikons' => 'ikona-'
		];

		$the_key='getCalendar_key_'.$date;
		if(false===($quote=get_transient($the_key)) || BG_CALENDAR_WIDGET_DEBUG) {
			
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
					$event->line = '<span class="feast'.$event->ideograph.'"><a title="'. $event->title .'" href="'. $event->url .'" target="_blank" rel="noopener">'.$event->title.'</a></span>';

					
					
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
							if (str_starts_with($event->title, 'Навече́рие')) {
								$event->type = 'eve';
								$event->ideograph = 8;				
								$prefeast_event = $event;
							}
							else $events[] = $event;
					}
				}

				// Святые
				foreach($data->saints as $saint) {

					$event = (object)array();
					// Название
					if (!empty($saint->title_genitive)) $title = trim(strip_tags($saint->title_genitive));
					elseif (!empty($saint->title)) $title = trim(strip_tags($saint->title));
					else continue;
					
					if ($saint->suffix) $title .= (($saint->suffix[0] == ',')?'':' ').trim(strip_tags($saint->suffix));
			
					if ($saint->prefix) $title = trim(strip_tags(mb_strtolower($saint->prefix))).' '.$title;
					elseif ($saint->type_of_sanctity) $title = trim(strip_tags($saint->type_of_sanctity)).' '.$title;
					
					// Имя
					$name = strip_tags($saint->name);
					
					// Лик святости
					if ($saint->group > 0) $sanctity = $saint->type_of_sanctity_plural;
					else $sanctity = $saint->type_of_sanctity;
					
					// Собираем святых в группы 
					$key = array_search($saint->group, array_column($events, 'group'));
					if ($saint->group && $key !== false) {
						$url = 'https://azbyka.ru/days/sv-'. $saint->uri;
						$line = '<span class="feast'.$saint->ideograph.'"><a title="'. $saint->title .'" href="'. $url .'" target="_blank" rel="noopener">'.$title.'</a></span>';
						$union = (!empty($events[$key]->union))?trim($events[$key]->union):',';
						if ($union[0] != ',') $union = ' '. $union;
						$events[$key]->title .= $union.' '.$title;
						$events[$key]->name .= $union.' '.$name;
						$events[$key]->line .= $union.' '.$line;
						// Список всех ID группы
						$event->id .= ',s'. $saint->id;
						// Если в группе разные лики святости, то принимаем общее название по первому святому в группе
						if ($events[$key]->sanctity != $sanctity) $events[$key]->sanctity = $types_of_sanctity[$events[$key]->sanctity];
						continue;
					}
					
					// Данные о группе от первого святого в списке
					$event->title = $title;
					$event->name = $name;
					$event->sanctity = $sanctity;
					$event->group = $saint->group;
					$event->split_group = $saint->split_group;
					$event->union = $saint->union;
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
					$event->line = '<span class="feast'.$event->ideograph.'"><a title="'. $saint->title .'" href="'. $saint->url .'" target="_blank" rel="noopener">'.$event->title.'</a></span>';
					
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
					$event->line = '<span class="feast'.$event->ideograph.'"><a title="'. $event->title .'" href="'. $event->url .'" target="_blank" rel="noopener">'.$event->title.'</a></span>';

					$events[] = $event;
				}
			}
			// Сортируем события
			usort($events, function($a, $b) {
				return strcmp($a->priority, $b->priority);
			});
			
		// Икона дня
			if (isset($instance['icon']) && $instance['icon'] ) {
				$allevents = $events;
				array_unshift($allevents, $special_event, $prefeast_event);
				foreach ($allevents as $event) {
					if (!empty($event->imgs)) {
						$image = '<div class="days-image">';
							$image .= '<a title="'. $event->title .'" href="'. $event->url .'" target="_blank" rel="noopener">';
							$image.= '<img src="'.$event->imgs[0]->preview_absolute_url.'" alt="'.$event->title.'">';
							$image .= '</a>';
						$image .= '</div>';
						break;
					}	
				}
			}
		// Дата
			$image .= '<span class="week_day"><a href="https://azbyka.ru/days/'. $date .'"'.(($wd==7)?' style="color:red"':"").'>'. $weekday[$wd-1] .',<br>'. (int) $d .' '. $monthes[$m-1] .' '. $y .'г.</a></span><br>';
			$image .= '('.(int) $old_d .' '. $monthes[$old_m-1] .' ст.ст.)<br>';
			if (!empty($special_event))	$image .= '<span class="round_week"><a title="'. $special_event->title .'" href="'. $special_event->url .'" target="_blank" rel="noopener">'.$special_event->title.'</a></span>';	
			else $image .= '<span class="round_week">'.strip_tags($data->fasting->round_week).'</span>';
			if (!empty($prefeast_event)) $image .= '<p class="prefeast"><a title="'. $prefeast_event->title .'" href="'. $prefeast_event->url .'" target="_blank" rel="noopener">'.strip_tags($prefeast_event->title).'</a></p>';
			
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
				$paragraph .= $symbol.$event->line.'; ';
			}
			if ($paragraph) $quote .= '<p>'.substr($paragraph, 0, -2).'.</p>'; 

		//Чтения дня
			if(isset($instance['readings']) && $instance['readings'] && !empty($data->texts) && !empty($preparedText = strip_tags($data->texts[0]->text,'<p><b><strong><i><em><a><br>'))){
				$quote .= '<hr>'.$preparedText.'<label class="btn-info"><input type="checkbox" class="btn-info-checkbox"><span class="btn-info-inner"><b>Чтения Св. Писания на богослужениях</b><br>Зач. - № <a href="https://azbyka.ru/zachala" target="_blank">зачала</a><br>Утр. - на <a href="https://azbyka.ru/utrenya" target="_blank">Утрени</a>.<br>Лит. - на <a href="https://azbyka.ru/liturgiya" target="_blank">Литургии</a>.</span><i class="fa fa-question-circle"></i></label>';
			}
			
		// Ссылки на богослужебные книги
			if (isset($instance['links']) && $instance['links'] ) {
				$mantle = array("janvar","fevral","mart","aprel","maj","iyun","iyul","avgust","sentjabr","oktjabr","nojabr","dekabr");
				$tip48 = array(5,6,7,8,9,10,11,12,1,2,3,4);
				$quote .= '<hr><a class="hlink" href="https://azbyka.ru/bogosluzhebnye-ukazaniya?date='.$y.'-'.$m.'-'.$d.'" target="_blank">Богослужебные указания ►</a>';
				$quote .= ' <a class="hlink" href="https://azbyka.ru/otechnik/Pravoslavnoe_Bogosluzhenie/tipikon/48_'.$tip48[$m-1].'" target="_blank">Типикон ►</a>';
				$quote .= ' <a class="calVoice hlink" target="_blank" href="https://azbyka.ru/otechnik/Pravoslavnoe_Bogosluzhenie/'.$this->worships($date).' Глас '.$this->getVoice($date).' ►</a>';
				// Минея
				if (!empty($mantle[$old_m-1])) 
					$quote .= ' <a class="hlink" href="https://azbyka.ru/otechnik/Pravoslavnoe_Bogosluzhenie/mineja-'.$mantle[$old_m-1].'/'.$old_d.'" target="_blank">Минея, '.$old_d.' '.$monthes[$old_m-1].' ►</a>';
			}
			
			$quote = '<div class="widget-title saints-title"><a href="/days/" title="Православный календарь" target="_blank" rel="noopener">Православный календарь</a></div>'.
					 '<div class="date-today">'.$image.'</div>'.
					 '<div class="saints">'.$quote.'</div>';

			set_transient( $the_key, $quote, 60*MINUTE_IN_SECONDS );
		}
		return $quote;
	}
	
	

	/*******************************************************************************
		Функция возвращает ссылки на службы в течение года
		Параметры:
			$date - дата в формате Y-m-d
	*******************************************************************************/  
	private function worships ($date) {
		$diff = $this->easter_diff($date);
		$wd = date("N",strtotime($date))+0; 
		$w = date("w",strtotime($date))+1; 
		$voice = $this->getVoice($date);
		
	// Постная триодь
		if ($diff ==  -70) {			// Неделя о мытаре и фарисее
			return 'sluzhby-predugotovitelnyh-sedmits/#0_1">Постная триодь. ';
		} else if ($diff ==  -63) {	// Неделя о блудном сыне
			return 'sluzhby-predugotovitelnyh-sedmits/#0_5">Постная триодь. ';
		} else if ($diff ==  -57) {	// В субботу мясопустную
			return 'sluzhby-predugotovitelnyh-sedmits/#0_9">Постная триодь. ';
		} else if ($diff ==  -56) {	// Неделя мясопустная
			return 'sluzhby-predugotovitelnyh-sedmits/#0_13">Постная триодь. ';
		} else if ($diff ==  -50) {	// В субботу сырную
			return 'sluzhby-predugotovitelnyh-sedmits/#0_17">Постная триодь. ';
		} else if ($diff ==  -49) {	// В неделю сыропустную
			return 'sluzhby-predugotovitelnyh-sedmits/#0_21">Постная триодь. ';
		} else if ($diff < -49) {	// Предуготовительные седмицы
			return 'oktoih/'.($voice+1).'">Октоих. ';
		} else if ($diff < -41) {	// Великий пост 1-я седмица
			return 'sluzhby-pervoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. ';
		} else if ($diff < -34) {	// Великий пост 2-я седмица
			return 'sluzhby-vtoroj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. ';
		} else if ($diff < -27) {	// Великий пост 3-я седмица
			return 'sluzhby-tretej-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. ';
		} else if ($diff < -20) {	// Великий пост 4-я седмица
			return 'sluzhby-chetvertoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. ';
		} else if ($diff < -13) {	// Великий пост 5-я седмица
			return 'sluzhby-pjatoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. ';
		} else if ($diff < -6) {		// Великий пост 6-я седмица
			return 'sluzhby-shestoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. ';
		} else if ($diff < 0) {		// Великий пост страстная седмица
			return 'sluzhby-strastnoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. ';

	// Цветная триодь
		} else if ($diff < 7) {		// Светлая седмица
			return 'sluzhby-svetloj-sedmitsy/'.$w.'">Цветная триодь. ';
		} else if ($diff < 14) {		// 2-я седмица по Пасхе
			return '/sluzhby-vtoroj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. ';
		} else if ($diff < 21) {		// 3-я седмица по Пасхе
			return 'sluzhby-tretej-sedmitsy-po-pashe/'.$w.'">Цветная триодь. ';
		} else if ($diff < 28) {		// 4-я седмица по Пасхе
			return 'sluzhby-chetvertoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. ';
		} else if ($diff < 35) {		// 5-я седмица по Пасхе
			return 'sluzhby-pjatoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. ';
		} else if ($diff < 42) {		// 6-я седмица по Пасхе
			return 'sluzhby-shestoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. ';
		} else if ($diff < 49) {		// 7-я седмица по Пасхе
			return 'sluzhby-sedmoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. ';
		} else if ($diff < 56) {		// 8-я седмица по Пасхе
			return 'sluzhby-vosmoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. ';
		} else if ($diff ==  56) {	// День всех Святых
			return 'sluzhby-vosmoj-sedmitsy-po-pashe/8">Цветная триодь. ';

	// Октоих 
		} else {
			return 'oktoih/'.($voice+1).'">Октоих. ';
		}
	}

	/*******************************************************************************
		Функция возвращает глас по Октоиху для указанной даты
		Параметры:
			$date - дата в формате Y-m-d
	*******************************************************************************/  
	private function getVoice($date) {
		list($year, $m, $d) = explode ('-', $date);
		$num = $this->easter_diff($date, $year);
		if ($num < 0) {									// Если дата раньше Пасхи этого года,
			$num = $this->easter_diff($date, $year-1);	// то отсчитываем от предыдущей Пасхи
		}
		if ($num < 7) $voice = $num+1;
		else $voice = floor(($num-7)/7)%8+1;
		return $voice;
	}
	/*******************************************************************************
		Функция возвращает количество дней между Пасхой и указанной датой по новому стилю
		Параметры:
			$date - дата в формате Y-m-d
	*******************************************************************************/  
	private function easter_diff($date, $year=0) {
		if (!$year) list($year, $m, $d) = explode ('-', $date);
		$interval = date_diff(date_create(bg_get_easter($year)), date_create($date));
		return (int)$interval->format('%R%a');
	}

}
 
/*****************************************************************************************

	Регистрация виджета
	
******************************************************************************************/
function bg_calendar_widget_load() {
	register_widget( 'bgCalendarWidget' );
}
add_action( 'widgets_init', 'bg_calendar_widget_load' );

