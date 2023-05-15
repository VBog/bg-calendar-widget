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
				xhr.onreadystatechange = function() {
					if (this.readyState != 4) return;
					document.getElementById("bg_desc_text").innerHTML = xhr.responseText;
					// Очистить div с текстом Библии
					document.getElementById("bg_hide_block1").addEventListener('click', function() {
						event.preventDefault();
						document.getElementById("bg_desc_text").innerHTML='';
					}, false);
					return false;
				}
				var desc = el.getAttribute('data-desc');
				xhr.send("desc="+desc);
			},
			false
		);
	});

	// Отправляем POST запрос с ссылкой на Библию 
	var els2 = document.getElementsByClassName("bg_bibleRef");
	Array.prototype.forEach.call(els2, function(el) {
		el.addEventListener("click",
			function() {
				var url="https://azbyka.ru/worships/calendar/api/bible.php";
				var xhr = new XMLHttpRequest();
				xhr.open("POST", url, false);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.onreadystatechange = function() {
					if (this.readyState != 4) return;
					document.getElementById("bg_bible_text").innerHTML = xhr.responseText;
					// Очистить div с текстом Библии
					document.getElementById("bg_hide_block2").addEventListener('click', function() {
						event.preventDefault();
						document.getElementById("bg_bible_text").innerHTML='';
					}, false);
					return false;
				}
				var ref = el.innerText;
				xhr.send("ref="+ref);
			},
			false
		);
	});
	
});
