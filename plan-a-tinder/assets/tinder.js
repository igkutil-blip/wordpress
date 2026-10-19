/* Plan A Tinder: poticaj umanjuje prikaz slobodnih mjesta (samo prikaz – prijava i dalje ide prema stvarnim mjestima). */
(function () {
	var minus = 0, busy = false;
	function apply() {
		if (!minus || busy) return;
		busy = true;
		document.querySelectorAll('.ttbm_available_seat').forEach(function (el) {
			var real = parseInt((document.getElementById('ttbm_total_available') || {}).textContent, 10);
			if (isNaN(real)) real = parseInt(el.getAttribute('data-pat-real') || el.textContent, 10);
			if (isNaN(real)) return;
			el.setAttribute('data-pat-real', real);
			var shown = real > 0 ? Math.max(1, real - minus) : 0;
			if (el.textContent.trim() !== String(shown)) el.textContent = shown;
		});
		busy = false;
	}
	document.addEventListener('DOMContentLoaded', function () {
		var card = document.querySelector('.pat-card[data-pat-minus]');
		minus = card ? parseInt(card.getAttribute('data-pat-minus'), 10) || 0 : 0;
		if (!minus) return;
		apply();
		// TTBM osvježi broj nakon odabira datuma (AJAX) – ponovno umanji.
		new MutationObserver(apply).observe(document.body, { childList: true, subtree: true, characterData: true });
	});
})();
