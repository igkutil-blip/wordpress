(function(){
  document.querySelectorAll('.aka-prog').forEach(function(s){
    var t=s.querySelector('.aka-track');if(!t)return;
    s.querySelectorAll('.aka-arrow').forEach(function(b){
      b.addEventListener('click',function(){
        var c=t.querySelector('.aka-course');var step=c?c.getBoundingClientRect().width+parseFloat(getComputedStyle(t).columnGap||24):t.clientWidth;
        var d=+b.getAttribute('data-aka-dir');
        if(d>0&&t.scrollLeft+t.clientWidth>=t.scrollWidth-4){t.scrollTo({left:0,behavior:'smooth'});return;}
        if(d<0&&t.scrollLeft<=4){t.scrollTo({left:t.scrollWidth,behavior:'smooth'});return;}
        t.scrollBy({left:d*step,behavior:'smooth'});
      });
    });
  });
})();
