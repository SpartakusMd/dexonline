<a class="widget aotm row" href="articol/{$articol}">
  <div class="col-lg-8 col-md-12 col-sm-12 col-xs-6">
    <h4>{t}article of the month{/t}</h4><br>
    <span class="widget-value">{$articol|urldecode|replace:'_':' '}</span>
  </div>
  <div class="col-lg-4 col-md-12 col-sm-12 col-xs-6 widget-thumbnail">
    <img
      alt="{t}article of the month{/t}"
      src="{Config::STATIC_URL}img/wotd/thumb88/misc/papirus.png"
      class="widget-icon">
  </div>
</a>
