/* Domestic – kartica proizvoda: ponavljajući redovi i odabir slike medalje */
jQuery(function ($) {
  $('.dsp-a-rep').each(function () {
    var rep = $(this), tpl = rep.find('.dsp-a-tpl').first().clone().removeClass('dsp-a-tpl');
    rep.find('.dsp-a-tpl').remove();
    tpl.find('input,textarea').val(''); tpl.find('select').prop('selectedIndex', 0); tpl.find('.dsp-a-img').html('<span>+ slika</span>');
    rep.on('click', '.dsp-a-add', function () { var n = tpl.clone(); n.insertBefore($(this)); n.find('input[type=text]').first().focus(); });
    rep.on('click', '.dsp-a-del', function () { $(this).closest('.dsp-a-item').remove(); });
    if (!rep.find('.dsp-a-item').length) rep.find('.dsp-a-add').trigger('click');
  });
  $(document).on('click', '.dsp-a-img', function (e) {
    e.preventDefault();
    var btn = $(this), item = btn.closest('.dsp-a-item');
    var frame = wp.media({ title: 'Slika medalje ili priznanja', button: { text: 'Odaberi' }, library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      var a = frame.state().get('selection').first().toJSON();
      item.find('.dsp-a-img-id').val(a.id);
      var u = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
      btn.html('<img src="' + u + '" alt="">');
    });
    frame.open();
  });
});
