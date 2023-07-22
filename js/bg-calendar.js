document.addEventListener('DOMContentLoaded', function(){ 
	// Очистить div с текстом Жития
	var bg_hide_block1 = document.getElementById("bg_hide_block1");
	if (bg_hide_block1) bg_hide_block1.addEventListener('click', function() {
		document.getElementById("bg_desc_text").innerHTML='';
	}, false);

	

	// Отправляем POST запрос с ссылкой на Жития 
	var els1 = document.getElementsByClassName("bg_descriptions");
	Array.prototype.forEach.call(els1, function(el) {
		el.addEventListener("click",
			function() {
				var url="https://azbyka.ru/worships/calendar/api/desc.php";
				var xhr = new XMLHttpRequest();
				xhr.open("POST", url, false);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				var desc = el.getAttribute('data-desc');
				document.getElementById("bg_desc_content").innerHTML = '<hr><div class="bg_hide_block"><input id="bg_hide_block1" type="button" value="&#215;" title="Скрыть"></div><div id="bg_desc_text"></div>';
				xhr.onreadystatechange = function() {
					if (this.readyState != 4) return;
					document.getElementById("bg_desc_text").innerHTML = xhr.responseText+'<br>';
					// Очистить div с текстом Библии
					document.getElementById("bg_hide_block1").addEventListener('click', function() {
						event.preventDefault();
						document.getElementById("bg_desc_content").innerHTML='';
					}, false);
					return false;
				}
				xhr.send("desc="+desc);
			},
			false
		);
	});

	// Отправляем POST запрос с ссылкой на Библию 
	var els2 = document.getElementsByClassName("bg_bibleRef");
	Array.prototype.forEach.call(els2, function(el) {
		el.addEventListener("click",
			function(e) {
				var url="https://azbyka.ru/worships/calendar/api/bible.php";
				var xhr = new XMLHttpRequest();
				xhr.open("POST", url, false);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				var ref = el.innerText;
				var href = el.getAttribute("data-ref");
				var hlink = '';
				if (href) {
					href = "'https://azbyka.ru/biblia/?"+href+"'";
					hlink = '<input id="bg_hlink" type="button" value="Перейти на Библию" title="Перейти на Библию" onclick="window.open('+href+');">';
				}
				document.getElementById("bg_bible_content").innerHTML = '<hr><div class="bg_hide_block">'+hlink +
					'<input id="bg_hide_block2" type="button" value="&#215;" title="Скрыть"></div><div id="bg_bible_text"></div>';
				xhr.onreadystatechange = function() {
					if (this.readyState != 4) return;
					document.getElementById("bg_bible_text").innerHTML = xhr.responseText+'<br>';
					// Очистить div с текстом Библии
					document.getElementById("bg_hide_block2").addEventListener('click', function() {
						event.preventDefault();
						document.getElementById("bg_bible_content").innerHTML='';
					}, false);
					return false;
				}
				xhr.send("ref="+ref);
			},
			false
		);
	});
	
});
