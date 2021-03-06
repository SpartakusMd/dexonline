{extends "layout-admin.tpl"}

{$title="Asistent pentru cuvântul zilei: {$yearMonth|date_format:'%B %Y'}"}

{block "title"}{$title}{/block}

{block "content"}
  <h3>
    {$title}

    <form class="form-inline pull-right">
      <div class="form-group">
        <label for="calendar">alege altă lună:</label>
        <input id="calendar" type="text" name="for" value="{$yearMonth}" class="form-control">
      </div>
      <button type="submit" class="btn btn-default">
        <i class="glyphicon glyphicon-ok"></i>
      </button>
    </form>
  </h3>

  <div class="voffset4"></div>

  {foreach $data as $day => $rec}
    <div class="panel panel-default {if $rec.allOk}panel-success{/if}">
      <div
        class="panel-heading {if $rec.allOk}collapsed{/if}"
        data-toggle="collapse"
        href="#collapseDay{$day}">
        <span class="date">
          {$day} {$yearMonth|date_format:'%B %Y'}
        </span>

        <div class="pull-right">
          <a
            class="btn btn-link btn-xs"
            href="https://ro.wikipedia.org/wiki/{$day}_{$yearMonth|date_format:'%B'}"
            target="_blank">wikipedia RO</a>
          <a
            class="btn btn-link btn-xs"
            href="https://en.wikipedia.org/wiki/{$enMonthName}_{$day}"
            target="_blank">wikipedia EN</a>

          {if $rec.allOk}
            <span class"pull-right">{$rec.thisYear[0]->lexicon}</span>
          {/if}
        </div>

      </div>

      <div id="collapseDay{$day}" class="panel-collapse collapse {if !$rec.allOk}in{/if}">
        <div class="panel-body">
          {if empty($rec.thisYear)}
            <div class="alert alert-danger" role="alert">
              Nu ai ales încă un cuvânt.
            </div>
          {else if count($rec.thisYear) > 1}
            <div class="alert alert-warning" role="alert">
              Există {$rec.thisYear|count} cuvinte.
            </div>
          {else if !$rec.thisYear[0]->defHtml}
            <div class="alert alert-warning" role="alert">
              Există un motiv, dar nu și o definiție.
            </div>
          {else if !$rec.thisYear[0]->description}
            <div class="alert alert-warning" role="alert">
              Există o definiție, dar nu și un motiv.
            </div>
          {/if}

          {foreach $rec.duplicates as $dup}
            <div
              class="alert {if $dup.exact}alert-danger{else}alert-warning{/if}"
              role="alert">
              {if $dup.exact}
                Un cuvânt identic,
              {else}
                Un cuvânt asemănător,
              {/if}
              <b>{$dup.oldLexicon}</b>, a fost programat pe
              {strip}
              <a href="../cuvantul-zilei/{$dup.oldDate}" class="alert-link">
                {$dup.oldDate|date_format:'%d %B %Y'}
              </a>.
              {/strip}
            </div>
          {/foreach}

          {foreach $rec.thisYear as $w}
            <p>
              {if $w->defHtml}
                {$w->defHtml}
              {/if}

              {if $w->description}
                <div>
                  <strong>Motiv</strong>: {$w->descHtml}
                </div>
              {/if}
            </p>
          {/foreach}

          {if !empty($rec.otherYears)}
            <hr>
            <h4>În alți ani:</h4>

            <table class="table table-condensed borderless">
              <tbody>
                {foreach $rec.otherYears as $w}
                  <tr>
                    <td>
                      {if $w->hasFullDate()}
                        {$w->displayDate|date_format:'%Y'}
                      {else}
                        <span class="label label-default">fără an</span>
                      {/if}
                    </td>
                    <td>{$w->lexicon}</td>
                    <td>{$w->descHtml}</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          {/if}

        </div>
      </div>
    </div>
  {/foreach}
{/block}
