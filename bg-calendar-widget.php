<?php
/* 
    Plugin Name: Bg Calendar Widget
    Plugin URI: https://bogaiskov.ru
    Description: Виджет православного календаря ("Азбука веры")
    Version: 1.0
    Author: VBog
    Author URI: https://bogaiskov.ru 
	License:     GPL2
	GitHub Plugin URI: https://github.com/VBog/Bg-Az-Counter-GitHub/
*/

/*  Copyright 2021  Vadim Bogaiskov  (email: vadim.bogaiskov@gmail.com)

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
define('BG_CALENDAR_WIDGET_VERSION', '1.0');

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
 
		$calendar = $this->getAzbykaCalendar ();
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
		</p>
		<?php 
	}
 
	// сохранение настроек виджета
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}

	// Формирование текста календаря
	private function getAzbykaCalendar () {

//		$date = bg_currentDate();
		if (isset($_GET["date"])) {		// Задана дата
			$date = $_GET["date"];
		} else {
			$date = date('Y-m-d', time());
		}

		$sufix = [
			'holidays' => 'prazdnik-',
			'saints' => 'sv-',
			'ikons' => 'ikona-'
		];

		$the_key='getCalendar_key_'.$date;
		if(false===($quote=get_transient($the_key))) {
			
			$main_feast = array();
			$feasts = array();
			$feast_type = ['Двунадесятый или Великий', 'Бденный',  'Полиелейный', 'Славословный', 'Шестеричный', 'Вседневный'];
			
			$quote = '<div class="saints">';
			$response = wp_remote_get( 'https://azbyka.ru/days/api/day/'.$date.'.json' );
			$json = wp_remote_retrieve_body($response);
			if ($json){
				$data = json_decode($json, false);
				// Найдем основной праздник, если он есть
				$ideograph = 99;
				$quote1 = '';
				foreach($data->holidays as $holiday) {
					if ($holiday->title_genitive) $title = strip_tags($holiday->full_title);
					else if ($holiday->title) $title = strip_tags($holiday->title);
					else continue;
					if ($holiday->ideograph) {
						$symbol ='<img src="'.plugins_url( 'img/S'.$holiday->ideograph.'.gif', __FILE__ ).'" title="'.$feast_type[$holiday->ideograph-1].' праздник" /> ';
						$name = '<span class="feast'.$holiday->ideograph.'">'.$title.'</span>';
					} else {
						$symbol ='';
						$name = $title;
					}
					$quote1 .= $symbol.'<a title="'. $title .'" href="https://azbyka.ru/days/prazdnik-'. $holiday->uri .'" target="_blank" rel="noopener">'.$name.'</a>, ';
					
					$holiday->type = 'holidays';
					$feasts[] = $holiday;
					if (!empty($holiday->ideograph) && $holiday->ideograph < $ideograph) {
						$ideograph = $holiday->ideograph;
						$main_feast = $holiday;
					}
				}
				if ($quote1) $quote .= '<p>'.substr($quote1, 0, -2).'.</p>'; 
				$quote2 = '';
				$priority = 0;
				foreach($data->saints as $saint) {
					if ($saint->title_genitive) $title = strip_tags($saint->title_genitive);
					else if ($saint->title) $title = strip_tags($saint->title);
					else continue;
					if ($saint->type_of_sanctity) $title = strip_tags($saint->type_of_sanctity).' '.$title;
					if ($saint->suffix) $title .= strip_tags($saint->suffix);
					if ($saint->ideograph) {
						$symbol ='<img src="'.plugins_url( 'img/S'.$saint->ideograph.'.gif', __FILE__ ).'" title="'.$feast_type[$saint->ideograph-1].' праздник" /> ';
						$name = '<span class="feast'.$saint->ideograph.'">'.$title.'</span>';
					} else {
						$symbol ='';
						$name = $title;
					}
					if ($quote2 && $priority < $saint->priority) {
						$quote .= '<p>'.substr($quote2, 0, -2).'.</p>'; 
						$priority = $saint->priority;
						$quote2 = '';
					}
					$quote2 .= $symbol.'<a title="'. $title .'" href="https://azbyka.ru/days/sv-'. $saint->uri .'" target="_blank" rel="noopener">'.$name.'</a>, ';
					
					$saint->type = 'saints';
					$feasts[] = $saint;
					if (!empty($saint->ideograph) && $saint->ideograph < $ideograph) {
						$ideograph = $saint->ideograph;
						$main_feast = $saint;
					}
				}
				if ($quote2) $quote .= '<p>'.substr($quote2, 0, -2).'.</p>'; 
				$quote3 = '';
				foreach($data->ikons as $ikon) {
					if ($ikon->title_genitive) $title = strip_tags($ikon->clean_title);
					else if ($ikon->title) $title = strip_tags($ikon->title);
					else continue;
					if ($ikon->type_of_sanctity) $title = strip_tags($ikon->type_of_sanctity).' '.$title;
					if ($ikon->suffix) $title .= strip_tags($ikon->suffix);
					if ($ikon->ideograph) {
						$symbol ='<img src="'.plugins_url( 'img/S'.$ikon->ideograph.'.gif', __FILE__ ).'" title="'.$feast_type[$ikon->ideograph-1].' праздник" /> ';
						$name = '<span class="feast'.$ikon->ideograph.'">иконы Богородицы '.$title.'</span>';
					} else {
						$symbol ='';
						$name = 'иконы Богородицы '.$title;
					}
					$quote3 .= $symbol.'<a title="'. $title .'" href="https://azbyka.ru/days/ikona-'. $ikon->uri .'" target="_blank" rel="noopener">'.$name.'</a>, ';
					
					$ikon->type = 'ikons';
					$feasts[] = $ikon;
					if (!empty($ikon->ideograph) && $ikon->ideograph < $ideograph) {
						$ideograph = $ikon->ideograph;
						$main_feast = $ikon;
					}
				}
				if ($quote3) $quote .= '<p>'.substr($quote3, 0, -2).'.</p>'; 
				$quote .= '</div>';

				if ($ideograph == 99 || empty($main_feast->imgs)) {				// Нет основного праздника
					$ideograph = 0; 
					foreach ($feasts as $feast) {
						if (!empty($feast->imgs)) {
							$main_feast = $feast;
							break;
						}
					}
				}
				if (!empty($main_feast->imgs)) {
					$image = '<div class="days-image">';
						$image .= '<a title="'. $main_feast->title .'" href="https://azbyka.ru/days/'. $sufix[$main_feast->type].$main_feast->uri .'" target="_blank" rel="noopener">';
							$image .= '<img alt="'. $main_feast->title .'" src="https://azbyka.ru/days/assets/img/'. $main_feast->type.'/'.$main_feast->id.'/'.$main_feast->imgs[0]->image .'">';
						$image .= '</a>';
					$image .= '</div>';
				}
				list($y,$m,$d) = explode ('-', $date);
				$monthes = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
				$image .= '<div class="date-today">';
					$image .= '<a href="https://azbyka.ru/days/'. $date .'">'. (int) $d .' '. $monthes[$m-1] .' '. $y .'г.</a>';
				$image .= '</div>';
				
				
				$quote = '<h3 class="saints-title"><a href="/days/" title="Православный календарь" target="_blank" rel="noopener">Православный календарь</a></h3>'.$image.$quote;
			}	
			set_transient( $the_key, $quote,  60*MINUTE_IN_SECONDS );
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

