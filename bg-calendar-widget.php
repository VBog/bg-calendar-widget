<?php
/* 
    Plugin Name: Bg Calendar Widget
    Plugin URI: https://bogaiskov.ru
    Description: Виджет православного календаря 
    Version: 3.2.2
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
define('BG_CALENDAR_WIDGET_VERSION', '3.2.2');

define('BG_CALENDAR_WIDGET_DEBUG', false);

define('BG_CALENDAR_WIDGET_LOG', dirname(__FILE__ ).'/bg_calendar_widget.log');

// Таблица стилей для плагина
function bg_calendar_widget_enqueue_frontend_styles () {
	wp_enqueue_style( "bg_calendar_widget_styles", plugins_url( '/css/styles.css', plugin_basename(__FILE__) ), array() , BG_CALENDAR_WIDGET_VERSION  );
}
add_action( 'wp_enqueue_scripts' , 'bg_calendar_widget_enqueue_frontend_styles' );

// JS скрипт 
function bg_calendar_widget_enqueue_frontend_scripts () {
	wp_enqueue_script( 'bg_calendar_widget_proc', plugins_url( 'js/bg-calendar.js', __FILE__ ), false, BG_CALENDAR_WIDGET_VERSION, true );
}	 
if ( !is_admin() ) {
	add_action( 'wp_enqueue_scripts' , 'bg_calendar_widget_enqueue_frontend_scripts' ); 
}

/*****************************************************************************************
	
	Виджет отображает в сайдбаре Православный календарь
	
******************************************************************************************/
class bgCalendarWidget extends WP_Widget {
 
	private $hlinks = false;
	
	// создание виджета
	function __construct() {
		parent::__construct(
			'bg_calendar_widget', 
			'Православный календарь', // заголовок виджета
			array( 'description' => 'Выводит в сайдбар Православный календарь с сайта "Азбука веры".' ) // описание
		);
	}
 
	// фронтэнд виджета
	public function widget( $args, $instance ) {
		if (isset($instance['links']) && $instance['links'] ) $this->hlinks = $instance['links'];
		
		$title = apply_filters( 'widget_title', $instance['title'] ); // к заголовку применяем фильтр (необязательно)
 
		echo $args['before_widget'];
 
		$calendar = $this->getAzbykaCalendar ( $instance );
		if ($calendar) {
?>
	<div class="widget-item">
		<div class="widget-inner bg-calendar-widget">
		<?php 
			if ( !empty( $title ) ) echo $args['before_title'] . $title . $args['after_title'];
			echo $calendar; 
		?>
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
			<label><input type="checkbox" id="<?php echo $this->get_field_id( 'descriptions' ); ?>" name="<?php echo $this->get_field_name( 'descriptions' ); ?>"<?php echo ( isset($instance['descriptions']) && $instance['descriptions'] ) ? ' checked' : '' ?>> Жития </label>
			<span>&nbsp;</span>
			<label><input type="checkbox" id="<?php echo $this->get_field_id( 'readings' ); ?>" name="<?php echo $this->get_field_name( 'readings' ); ?>"<?php echo ( isset($instance['readings']) && $instance['readings'] ) ? ' checked' : '' ?>> Чтения </label>
			<span>&nbsp;</span>
			<label><input type="checkbox" id="<?php echo $this->get_field_id( 'tropary' ); ?>" name="<?php echo $this->get_field_name( 'tropary' ); ?>"<?php echo ( isset($instance['tropary']) && $instance['tropary'] ) ? ' checked' : '' ?>> Тропари </label>
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
		$instance['descriptions'] = isset($new_instance['descriptions']) ? filter_var( $new_instance['descriptions'], FILTER_VALIDATE_BOOLEAN ) : false;
		$instance['readings'] = isset($new_instance['readings']) ? filter_var( $new_instance['readings'], FILTER_VALIDATE_BOOLEAN ) : false;
		$instance['tropary'] = isset($new_instance['tropary']) ? filter_var( $new_instance['tropary'], FILTER_VALIDATE_BOOLEAN ) : false;
		$instance['links'] = isset($new_instance['links']) ? filter_var( $new_instance['links'], FILTER_VALIDATE_BOOLEAN ) : false;
		
		return $instance;
	}

	// Формирование текста календаря
	private function getAzbykaCalendar ( $instance ) {

		$weekday = ['Понедельник','Вторник','Среда','Четверг','Пятница','Суббота','<span>Воскресенье</span>'];
		$monthes = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
		
		if (function_exists('bg_currentDate')) $date = bg_currentDate();
		else {
			if (isset($_GET["date"])) {		// Задана дата
				$date = $_GET["date"];
			} else {
				$date = date('Y-m-d');
			}
		}
		
		$date = apply_filters('bg_calendar_widget_date', $date);
		
		list($y, $m, $d) = explode('-', $date);
		$y = (int)$y; 
		$wd = date("N",strtotime($date));
		$tone = $this->bg_getTone($date);
		$easter = $this->bg_get_easter($y);

		$dd = ($y-$y%100)/100 - ($y-$y%400)/400 - 2;
		$old = date("Y-m-d",strtotime ($date.' - '.$dd.' days')) ;
		list($old_y,$old_m,$old_d) = explode ('-', $old);

		$json = $this->bg_get_calendar_data($date);
		$data = json_decode($json, true);
//print_dump('data', $data);
		$tomorrow = date ('Y-m-d', strtotime($date.'+ 1 days'));
		$json_tomorrow = $this->bg_get_calendar_data($tomorrow);
		$data_tomorrow = json_decode($json_tomorrow, true);

		ob_start();
		?>
		<div class="calendar">
		<?php if (isset($instance['icon']) && $instance['icon'] ) : ?>
		<!-- Икона дня -->
			<div id="icon-pics">
				<div class="icon"><img height="230" src="https://azbyka.ru/worships/calendar/images/<?php echo $data['icon']; ?>" title="<?php echo $data['icon_title']; ?>" alt="<?php echo $data['icon_title']; ?>" /></div>
			</div>
		<?php endif; ?>
		<!-- Дата по новому стилю -->
			<h3<?php echo (($wd==7)?' style=" color:red"':""); ?>><?php echo $weekday[$wd-1].', '. sprintf (_('%1$d %2$s %3$d г.'), (int)$d , $monthes[$m-1] , (int)$y); ?></h3>
		<!-- и по старому стилю -->
			<p<?php echo (($wd==7)?' style=" color:red"':""); ?>><?php echo '('.sprintf (_('%1$d %2$s ст.ст.'), (int)$old_d, $monthes[$old_m-1]).')'; ?></p>
		<!-- Название седмицы/Недели -->
			<h4<?php echo (($wd==7)?' style=" color:red"':""); ?>><?php echo $data['sedmica']; ?></h4>
		<!-- Глас, пост, пища -->
			<p><?php echo _("Глас").' '.$data['tone']; ?>, <?php echo $data['food']; ?></p>
		
		<?php
		$level_name = [_('Двунадесятый'), _('Великий'), _('Бденный'), _('Полиелейный'), _('Славословный'), _('Шестеричный'), _('Вседневный'), _('Особый')];
		/*******************************************************
			Выводим названия событий пятью абзацами.
				1. Есть служба в Минее/Триоди
				2. Память общих святых
				3. Память новомучеников
				4. Почитание икон Богородицы
				5. Прочие
		********************************************************/
		// Внимание, данные с приоритетом 0 на экран не выводим (только чтения)
		for ($i=1; $i<6; $i++) {
			$text = '';
			foreach ($data['events'] as $event) {
				$title = (in_array($event['level'], [1,8]))?('<b>'.$event['title'].'</b>'):$event['title'];
				$title = '<span'.(($event['level'] < 3)?' style=" color:red"':"").'>'.$title.'</span>';
				if ($event['priority'] == $i) {
					$symbol = ($event['level'] < 7)?('<img src="'.plugins_url( 'img/S'.$event['level'].'.gif', __FILE__ ).'" title="'.$level_name[$event['level']].'" alt="'.$level_name[$event['level']].'" /> '):'';
					if (isset($instance['descriptions']) && $instance['descriptions'] && !empty($event['description'])) { 
						$desc_img = ' <a href="#bg_desc_text"><span class="bg_descriptions" data-desc="'.$event['id_list'].'"><img src="'.plugins_url( 'img/L.gif', __FILE__ ).'" title="Житие" alt="Житие" /></span></a>';
					} else $desc_img = '';
					$text .= $symbol. $title.$desc_img.'. ';
				}
			}
			if ($text) echo '<p>'.$text.'</p>';
		}
		?>
		</div>
		<?php if (isset($instance['descriptions']) && $instance['descriptions'] ) : ?>
	<!-- Текст Жития -->
		<div id="bg_desc_content"></div>
		<?php 
		endif;
	/*******************************************************
		Выводим чтения суточного круга
	********************************************************/
		if (isset($instance['readings']) && $instance['readings'] ) : 
		?>
		<hr>
		<div class='readings'>
		<?php
	// Тип литургии 
		$liturgy = [_("Нет литургии.") ,_("Литургия свт. Иоанна Златоуста."), _("Литургия свт. Василия Великого."), _("Литургия Преждеосвященных Даров.")];
		echo '<p><i>'.$liturgy[$data['liturgy']].'</i></p>';

	// Список чтений дня
		// Праздники
		foreach ($data['events'] as $event) {
			if (!in_array($data['day_subtype'], ['universal_saturday', 'eve'])) {
				if ($wd == 6 || (is_numeric($event['priority']) && $event['level'] < 3 && $wd < 7)) { // Суббота или Бдение и выше
					$this->bg_printReadings ($event['readings'], false);
				}
			}
		}
		// Рядовые
		foreach ($data['ordinary_readings'] as $readings) {
			$this->bg_printReadings ($readings);
		}
		// Праздники
		foreach ($data['events'] as $event) {
			if (!in_array($data['day_subtype'], ['universal_saturday', 'eve'])) {
				if ($wd != 6 && is_numeric($event['priority']) && !($event['level'] < 3 && $wd < 7)) { // Не суббота и Полиелей и ниже
					$this->bg_printReadings ($event['readings'], false);
				}
			} else {
				if (in_array($event['subtype'], ['universal_saturday', 'eve'])) {
					$this->bg_printReadings ($event['readings'], false);
				}
			}
			
		}
		foreach ($data_tomorrow['events'] as $event) {
			$this->bg_printEvReadings ($event['readings']);
		}
		?>
		</div>
		<!-- Текст Библии -->
		<div id="bg_bible_content"></div>


		<?php 
		endif;
	/*******************************************************
		Выводим тропари, кондаки, молитвы и величания
	********************************************************/
		if (isset($instance['tropary']) && $instance['tropary'] ) : 
		?>
		<div class='tropary'>
		<hr>
		<h3><?php echo _("Тропари, кондаки, молитвы и величания"); ?></h3>
		<?php 
		// Тропари и кондаки дня
		$event = $data['tropary_day'];
		if (!empty($event['taks']) && !empty($event['taks'][0])) {
			echo '<details><summary>'._("Тропари и кондаки дня").'</summary><hr>'.PHP_EOL;
			echo '<div class="bg_content">'.PHP_EOL;
			foreach ($event['taks'] as $tak) {
				echo '<h4>'.$tak['title'].($tak['voice']?(', '._("глас").' '.$tak['voice']):'').'</h4>'.PHP_EOL;
				echo '<p>'.$tak['text'].'</p>'.PHP_EOL;
			}
			echo '</div><hr></details>'.PHP_EOL;
		}
	 
		// Тропари и кондаки событий календаря
		foreach ($data['events'] as $event) {
			if (!empty($event['taks']) && !empty($event['taks'][0])) {
				$title = $event['taks'][0]['title'];	// В заголовок выносим название первой записи без первого слова (Тропарь)
				$title = count(explode(' ',$title,2))>1?explode(' ',$title,2)[1]:'';
				echo '<details><summary>'.$title.'</summary><hr>'.PHP_EOL;
				echo '<div class="bg_content">'.PHP_EOL;
				foreach ($event['taks'] as $tak) {
					echo '<h4>'.$tak['title'].($tak['voice']?(', '._("глас").' '.$tak['voice']):'').'</h4>'.PHP_EOL;
					echo '<p>'.$tak['text'].'</p>'.PHP_EOL;
				}
			echo '</div><hr></details>'.PHP_EOL;
			}
		}
		?>		
		</div>
		<?php
		endif;
	/*******************************************************
		Выводим ссылки на богослужебные книги
	********************************************************/
		if (isset($instance['links']) && $instance['links'] ) :
		$mantle = array("janvar","fevral","mart","aprel","maj","iyun","iyul","avgust","sentjabr","oktjabr","nojabr","dekabr");
		$tip48 = array(5,6,7,8,9,10,11,12,1,2,3,4);
		$wday = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
		$old_d = (int) $old_d;
		$old_m = (int) $old_m;
		?>
		<div class='links'>
			<hr>
			<a class="hlink" href="https://azbyka.ru/bogosluzhebnye-ukazaniya?date=<?php echo $date; ?>" target="_blank">Богослужебные указания&nbsp;►</a>
			<a class="hlink" href="https://azbyka.ru/otechnik/Pravoslavnoe_Bogosluzhenie/tipikon/48/#48_<?php echo $tip48[$old_m-1].'_'.$old_d; ?>" target="_blank">Типикон, <?php echo $old_d.'&nbsp;'.$monthes[$old_m-1]; ?>&nbsp;►</a>
			<a class="calVoice hlink" target="_blank" href="https://azbyka.ru/otechnik/Pravoslavnoe_Bogosluzhenie/<?php echo $this->worships($date).', '.$wday[$wd-1]; ?>&nbsp;►</a>
			
			<?php if (!empty($mantle[$old_m-1])) { ?>
				<a class="hlink" href="https://azbyka.ru/otechnik/Pravoslavnoe_Bogosluzhenie/mineja-<?php echo $mantle[$old_m-1].'/'.$old_d; ?>" target="_blank">Минея, <?php echo $old_d.'&nbsp;'.$monthes[$old_m-1]; ?>&nbsp;►</a>
			<?php } ?>
		</div>
		<?php	
		endif;

		$quote = ob_get_contents();
		ob_end_clean();

		return $quote;
	}
	
	/*******************************************************************************************

		Функция получает данные с сайта Календаря 
		
	********************************************************************************************/
	private function bg_get_calendar_data ($date='') {
		$json = '';
		$the_key = 'calendar_data_'.$date;
		if(false===($json=get_transient($the_key)) || BG_CALENDAR_WIDGET_DEBUG || WP_DEBUG) {
			$response = wp_remote_get( 'https://azbyka.ru/worships/calendar/api/'.$date, ['timeout' => 120,]);
			// Проверим на ошибки
			if ( is_wp_error( $response ) ) {
				error_log($date.' '.$response->get_error_message().PHP_EOL, 3, BG_CALENDAR_WIDGET_LOG);
				return '';
			}
			$json = wp_remote_retrieve_body($response);
			set_transient( $the_key, $json, 24*HOUR_IN_SECONDS );
		}
		return $json;
	}

	/*************************************************************************************

		Функция выводит ссылки на чтения Св.Писания
			
	**************************************************************************************/
	// Всего дня 
	private function bg_printReadings ($readings, $evening=true) {
		if (empty($readings)) return;
		$text =
			(!empty($readings['morning'])?('<i>'._("Утр.").':</i> '.$this->blink ($readings['morning']).' '):'').
			(!empty($readings['hour1'])?('<i>'._("1-й час").':</i> '.$this->blink ($readings['hour1']).' '):'').
			(!empty($readings['hour3'])?('<i>'._("3-й час").':</i> '.$this->blink ($readings['hour3']).' '):'').
			(!empty($readings['hour6'])?('<i>'._("6-й час").':</i> '.$this->blink ($readings['hour6']).' '):'').
			(!empty($readings['hour9'])?('<i>'._("9-й час").':</i> '.$this->blink ($readings['hour9']).' '):'').
			(!empty($readings['apostle'])?('<i>'._("Лит.").': '._("Ап.").'-</i> '.$this->blink ($readings['apostle']).' '):'').
			(!empty($readings['gospel'])?('<i>'._("Ев.").'-</i> '.$this->blink ($readings['gospel']).' '):'').
			($evening && !empty($readings['evening'])?('<i>'._("Веч.").':</i> '.$this->blink ($readings['evening']).' '):'');

		echo $text?('<p>'.(!empty($readings['title'])?('<i>'.$readings['title'].':</i> '):'').$text.'</p>'):'';
	}
	// Вечера
	private function bg_printEvReadings ($readings) {
		if (empty($readings)) return;
		$text = (!empty($readings['evening'])?('<i>'._("Веч.").':</i> '.$this->blink ($readings['evening']).' '):'');

		echo $text?('<p>'.(!empty($readings['title'])?('<i>'.$readings['title'].':</i> '):'').$text.'</p>'):'';
	}

	/*************************************************************************************
		Функция переводит абревиатуру книг на язык локали и формирует гиперссылки на сайт Библии

		Параметры:
			$reference - ссылка на Библию на русском языке
			
		Возвращает ссылку на отрывок Св.Писания
			
	**************************************************************************************/
	// 
	private function blink ($reference) {
		$bg_bibrefs_abbr = array(		// Стандартные обозначение книг Священного Писания
			// Ветхий Завет
			// Пятикнижие Моисея															
			'Gen'		=>"Быт", 
			'Ex'		=>"Исх", 
			'Lev'		=>"Лев",
			'Num'		=>"Чис",
			'Deut'		=>"Втор",
			// «Пророки» (Невиим) 
			'Nav'		=>"Нав",
			'Judg'		=>"Суд",
			'Rth'		=>"Руф",
			'1Sam'		=>"1Цар",
			'2Sam'		=>"2Цар",
			'1King'		=>"3Цар",
			'2King'		=>"4Цар",
			'1Chron'	=>"1Пар",
			'2Chron'	=>"2Пар",
			'Ezr'		=>"1Езд",
			'Nehem'		=>"Неем",
			'Est'		=>"Есф",
			// «Писания» (Ктувим)
			'Job'		=>"Иов",
			'Ps'		=>"Пс",
			'Prov'		=>"Притч", 
			'Eccl'		=>"Еккл",
			'Song'		=>"Песн",
			'Is'		=>"Ис",
			'Jer'		=>"Иер",
			'Lam'		=>"Плч",
			'Ezek'		=>"Иез",
			'Dan'		=>"Дан",	
			// Двенадцать малых пророков 
			'Hos'		=>"Ос",
			'Joel'		=>"Иоил",
			'Am'		=>"Ам",
			'Avd'		=>"Авд",
			'Jona'		=>"Ион",
			'Mic'		=>"Мих",
			'Naum'		=>"Наум",
			'Habak'		=>"Авв",
			'Sofon'		=>"Соф",
			'Hag'		=>"Аг",
			'Zah'		=>"Зах",
			'Mal'		=>"Мал",
			// Второканонические книги
			'1Mac'		=>"1Мак",
			'2Mac'		=>"2Мак",
			'3Mac'		=>"3Мак",
			'Bar'		=>"Вар",
			'2Ezr'		=>"2Езд",
			'3Ezr'		=>"3Езд",
			'Judf'		=>"Иудиф",
			'pJer'		=>"ПослИер",
			'Solom'		=>"Прем",
			'Sir'		=>"Сир",
			'Tov'		=>"Тов",
			// Новый Завет
			// Евангилие
			'Mt'		=>"Мф",
			'Mk'		=>"Мк",
			'Lk'		=>"Лк",
			'Jn'		=>"Ин",
			// Деяния и послания Апостолов
			'Act'		=>"Деян",
			'Jac'		=>"Иак",
			'1Pet'		=>"1Пет",
			'2Pet'		=>"2Пет",
			'1Jn'		=>"1Ин", 
			'2Jn'		=>"2Ин",
			'3Jn'		=>"3Ин",
			'Juda'		=>"Иуд",
			// Послания апостола Павла
			'Rom'		=>"Рим",
			'1Cor'		=>"1Кор",
			'2Cor'		=>"2Кор",
			'Gal'		=>"Гал",
			'Eph'		=>"Еф",
			'Phil'		=>"Флп",
			'Col'		=>"Кол",
			'1Thes'		=>"1Сол",
			'2Thes'		=>"2Сол",
			'1Tim'		=>"1Тим",
			'2Tim'		=>"2Тим",
			'Tit'		=>"Тит",
			'Phlm'		=>"Флм",
			'Hebr'		=>"Евр",
			'Apok'		=>"Отк");


		$bg_bibrefs_translate = array(		// Перевод обозначений книг Священного Писания
			// Ветхий Завет
			// Пятикнижие Моисея															
			'Gen'		=>_("Быт"), 
			'Ex'		=>_("Исх"), 
			'Lev'		=>_("Лев"),
			'Num'		=>_("Чис"),
			'Deut'		=>_("Втор"),
			// «Пророки» (Невиим) 
			'Nav'		=>_("Нав"),
			'Judg'		=>_("Суд"),
			'Rth'		=>_("Руф"),
			'1Sam'		=>_("1Цар"),
			'2Sam'		=>_("2Цар"),
			'1King'		=>_("3Цар"),
			'2King'		=>_("4Цар"),
			'1Chron'	=>_("1Пар"),
			'2Chron'	=>_("2Пар"),
			'Ezr'		=>_("1Езд"),
			'Nehem'		=>_("Неем"),
			'Est'		=>_("Есф"),
			// «Писания» (Ктувим)
			'Job'		=>_("Иов"),
			'Ps'		=>_("Пс"),
			'Prov'		=>_("Притч"), 
			'Eccl'		=>_("Еккл"),
			'Song'		=>_("Песн"),
			'Is'		=>_("Ис"),
			'Jer'		=>_("Иер"),
			'Lam'		=>_("Плч"),
			'Ezek'		=>_("Иез"),
			'Dan'		=>_("Дан"),	
			// Двенадцать малых пророков 
			'Hos'		=>_("Ос"),
			'Joel'		=>_("Иоил"),
			'Am'		=>_("Ам"),
			'Avd'		=>_("Авд"),
			'Jona'		=>_("Ион"),
			'Mic'		=>_("Мих"),
			'Naum'		=>_("Наум"),
			'Habak'		=>_("Авв"),
			'Sofon'		=>_("Соф"),
			'Hag'		=>_("Аг"),
			'Zah'		=>_("Зах"),
			'Mal'		=>_("Мал"),
			// Второканонические книги
			'1Mac'		=>_("1Мак"),
			'1Mac'		=>_("2Мак"),
			'3Mac'		=>_("3Мак"),
			'Bar'		=>_("Вар"),
			'2Ezr'		=>_("2Езд"),
			'3Ezr'		=>_("3Езд"),
			'Judf'		=>_("Иудиф"),
			'pJer'		=>_("ПослИер"),
			'Solom'		=>_("Прем"),
			'Sir'		=>_("Сир"),
			'Tov'		=>_("Тов"),
			// Новый Завет
			// Евангилие
			'Mt'		=>_("Мф"),
			'Mk'		=>_("Мк"),
			'Lk'		=>_("Лк"),
			'Jn'		=>_("Ин"),
			// Деяния и послания Апостолов
			'Act'		=>_("Деян"),
			'Jac'		=>_("Иак"),
			'1Pet'		=>_("1Пет"),
			'2Pet'		=>_("2Пет"),
			'1Jn'		=>_("1Ин"), 
			'2Jn'		=>_("2Ин"),
			'3Jn'		=>_("3Ин"),
			'Juda'		=>_("Иуд"),
			// Послания апостола Павла
			'Rom'		=>_("Рим"),
			'1Cor'		=>_("1Кор"),
			'2Cor'		=>_("2Кор"),
			'Gal'		=>_("Гал"),
			'Eph'		=>_("Еф"),
			'Phil'		=>_("Флп"),
			'Col'		=>_("Кол"),
			'1Thes'		=>_("1Сол"),
			'2Thes'		=>_("2Сол"),
			'1Tim'		=>_("1Тим"),
			'2Tim'		=>_("2Тим"),
			'Tit'		=>_("Тит"),
			'Phlm'		=>_("Флм"),
			'Hebr'		=>_("Евр"),
			'Apok'		=>_("Отк"));


		$bg_bibrefs_name = array_flip($bg_bibrefs_abbr);
		
		$reference = preg_replace('/((\xA0)|\s)+/u', '', $reference); // Уберем пробелы

		$refs = explode (';', $reference);			// Несколько ссылок разделенных точкой с запятой
		$hlink = '';
		foreach($refs as $ref) {
			list($name, $ch) = explode('.',$ref);	// Разделим ссылку на аббревиатуру и номера глав и стихов

			$abbr = $bg_bibrefs_name[$name];		// Английская аббревиатура книги 
			$book = $bg_bibrefs_translate[$abbr];	// Перевод названия книги
			
			// Формируем ссылки на Писание
			$hlink .= '<span class="bg_bibleRef" data-ref="'.($this->hlinks?($abbr.'.'.$ch):'').'" title="'._("Показать текст").'">'.$book.'.'.$ch.'</span>; ';
		}
		$hlink = substr($hlink,0,-2);
		return $hlink;
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
		$tone = $this->bg_getTone($date);
		
	// Постная триодь
		if ($diff ==  -70) {			// Неделя о мытаре и фарисее
			return 'sluzhby-predugotovitelnyh-sedmits/#0_1">Постная триодь. Неделя о мытаре и фарисее';
		} else if ($diff ==  -63) {	// Неделя о блудном сыне
			return 'sluzhby-predugotovitelnyh-sedmits/#0_5">Постная триодь. Неделя о блудном сыне';
		} else if ($diff ==  -57) {	// В субботу мясопустную
			return 'sluzhby-predugotovitelnyh-sedmits/#0_9">Постная триодь. В субботу мясопустную';
		} else if ($diff ==  -56) {	// Неделя мясопустная
			return 'sluzhby-predugotovitelnyh-sedmits/#0_13">Постная триодь. Неделя мясопустная';
		} else if ($diff ==  -50) {	// В субботу сырную
			return 'sluzhby-predugotovitelnyh-sedmits/#0_17">Постная триодь. В субботу сырную';
		} else if ($diff ==  -49) {	// В неделю сыропустную
			return 'sluzhby-predugotovitelnyh-sedmits/#0_21">Постная триодь. В неделю сыропустную';
		} else if ($diff < -49) {	// Предуготовительные седмицы
			return 'oktoih/'.($tone+1).'">Октоих. Глас '.$this->bg_getTone($date);
		} else if ($diff < -41) {	// Великий пост 1-я седмица
			return 'sluzhby-pervoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. 1&nbsp;седмица';
		} else if ($diff < -34) {	// Великий пост 2-я седмица
			return 'sluzhby-vtoroj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. 2&nbsp;седмица';
		} else if ($diff < -27) {	// Великий пост 3-я седмица
			return 'sluzhby-tretej-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. 3&nbsp;седмица';
		} else if ($diff < -20) {	// Великий пост 4-я седмица
			return 'sluzhby-chetvertoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. 4&nbsp;седмица';
		} else if ($diff < -13) {	// Великий пост 5-я седмица
			return 'sluzhby-pjatoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. 5&nbsp;седмица';
		} else if ($diff < -6) {		// Великий пост 6-я седмица
			return 'sluzhby-shestoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. 6&nbsp;седмица';
		} else if ($diff < 0) {		// Великий пост страстная седмица
			return 'sluzhby-strastnoj-sedmitsy-velikogo-posta/'.$wd.'">Постная триодь. Страстная седмица';

	// Цветная триодь
		} else if ($diff < 7) {		// Светлая седмица
			return 'sluzhby-svetloj-sedmitsy/'.$w.'">Цветная триодь. Светлая седмица';
		} else if ($diff < 14) {		// 2-я седмица по Пасхе
			return '/sluzhby-vtoroj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. 2&nbsp;седмица';
		} else if ($diff < 21) {		// 3-я седмица по Пасхе
			return 'sluzhby-tretej-sedmitsy-po-pashe/'.$w.'">Цветная триодь. 3&nbsp;седмица';
		} else if ($diff < 28) {		// 4-я седмица по Пасхе
			return 'sluzhby-chetvertoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. 4&nbsp;седмица';
		} else if ($diff < 35) {		// 5-я седмица по Пасхе
			return 'sluzhby-pjatoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. 5&nbsp;седмица';
		} else if ($diff < 42) {		// 6-я седмица по Пасхе
			return 'sluzhby-shestoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. 6&nbsp;седмица';
		} else if ($diff < 49) {		// 7-я седмица по Пасхе
			return 'sluzhby-sedmoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. 7&nbsp;седмица';
		} else if ($diff < 56) {		// 8-я седмица по Пасхе
			return 'sluzhby-vosmoj-sedmitsy-po-pashe/'.$w.'">Цветная триодь. 8&nbsp;седмица';
		} else if ($diff ==  56) {	// День всех Святых
			return 'sluzhby-vosmoj-sedmitsy-po-pashe/8">Цветная триодь. День всех Святых';

	// Октоих 
		} else {
			return 'oktoih/'.($tone+1).'">Октоих. Глас '.$this->bg_getTone($date);
		}
	}

	/*******************************************************************************
		Функция возвращает глас по Октоиху для указанной даты
		Параметры:
			$date - дата в формате Y-m-d
		Возвращает:
			Глас по Октоиху	
	*******************************************************************************/  
	private function bg_getTone($date) {
		list($year, $m, $d) = explode ('-', $date);
		$num = $this->easter_diff($date, $year);
		if ($num < 0) {									// Если дата раньше Пасхи этого года,
			$num = $this->easter_diff($date, $year-1);	// то отсчитываем от предыдущей Пасхи
		}
		if ($num < 7) $tone = $num+1;
		else $tone = floor(($num-7)/7)%8+1;
		return $tone;
	}

	/*******************************************************************************
		Функция определяет день Пасхи или переходящий праздник в указанном году
		Параметры:
			$year - год в формате Y
			$shift - смещение даты относительно Пасхи (переходящий праздник)
					по умолчанию $shift=0 - день Пасхи
		Возвращает:
			Дату Пасхи или переходящий праздник в формате Y-m-d	
	*******************************************************************************/
	private function bg_get_easter($year, $shift=0) {
		$year = (int) $year;
		$a=((19*($year%19)+15)%30);
		$b=((2*($year%4)+4*($year%7)+6*$a+6)%7);
		if ($a+$b>9) {
			$day=$a+$b-9;
			$month=4;
		} else {
			$day=22+$a+$b;
			$month=3;
		}
		$dd = ($year-$year%100)/100 - ($year-$year%400)/400 - 2;
		
		return date( 'Y-m-d', mktime ( 0, 0, 0, $month, $day+$dd+intval($shift), intval($year) ) );
	}

	/*******************************************************************************
		Функция возвращает количество дней между Пасхой и указанной датой по новому стилю
		Параметры:
			$date - дата в формате Y-m-d
	*******************************************************************************/  
	private function easter_diff($date, $year=0) {
		if (!$year) list($year, $m, $d) = explode ('-', $date);
		$interval = date_diff(date_create($this->bg_get_easter($year)), date_create($date));
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

