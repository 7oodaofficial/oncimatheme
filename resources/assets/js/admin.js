jQuery(function($) {
  var template = '<tr class="tr-grabber-row">\
    <td class="moved"><span class="dashicons dashicons-sort"></span></td>\
    <td>\
      <button type="button" class="button trgrabberbt_a current" data-id="1"><span class="dashicons dashicons-format-video"></span></button>\
      <button type="button" class="button trgrabberbt_b" data-id="2"><span class="dashicons dashicons-download"></span></button>\
      <input type="hidden" name="trgrabber_type[]" value="1">\
    </td>\
    <td>\
      <select name="trgrabber_lang[]"><option value="">' + OnwatchAdmin.none + '</option></select>\
    </td>\
    <td>\
      <select name="trgrabber_quality[]"><option value="">' + OnwatchAdmin.none + '</option></select>\
    </td>\
    <td><input type="text" name="trgrabber_link[]" value="" class="regular-text" style="width:100%"></td>\
    <td class="tdoptns">\
      <input type="text" name="trgrabber_date[]" value="" placeholder="dd/mm/YYYY" style="width:100px">\
      <button type="button" class="button trgrabber_removelink"><span class="dashicons dashicons-dismiss"></span></button>\
    </td>\
  </tr>';

  $('#trgrabber_addlink').on('click', function() {
    $('#tr-grabber-content-links').append(template);
    $('.TrGrabber-tblcn .tr-grabber-row:last select').each(function() {
      var name = $(this).attr('name');
      if (name.indexOf('lang') > -1) {
        $(this).append($('select[name="trgrabber_lang[]"] option').clone());
      } else if (name.indexOf('quality') > -1) {
        $(this).append($('select[name="trgrabber_quality[]"] option').clone());
      }
    });
  });

  $(document).on('click', '.trgrabber_removelink', function() {
    $(this).closest('tr').remove();
  });

  $(document).on('click', '.trgrabberbt_a, .trgrabberbt_b', function() {
    var val = $(this).data('id');
    $(this).closest('td').find('.trgrabberbt_a, .trgrabberbt_b').removeClass('current');
    $(this).addClass('current');
    $(this).closest('td').find('input[type=hidden]').val(val);
  });

  $('#tr-grabber-content-links').sortable({
    handle: '.moved',
    items: 'tr',
    axis: 'y',
    cursor: 'move'
  });

  var currentTmdbId = OnwatchAdmin.tmdb_id || 0;
  var currentType = OnwatchAdmin.post_type === 'series' ? 'tv' : 'movie';

  if (currentTmdbId) {
    $('#tmdb_actions').show();
    if (currentType === 'movie') {
      $('#tmdb_import_seasons, #tmdb_import_episodes, #tmdb_import_all, #tmdb_quick_links').hide();
    } else {
      $('#tmdb_import_seasons, #tmdb_import_episodes, #tmdb_import_all, #tmdb_quick_links').show();
    }
  }

  $('.tr_grabber_go').on('click', function() {
    var id = $('#trgrabber_id').val();
    if (!id) { alert(OnwatchAdmin.empty); return; }
    var btn = $(this);
    btn.text(OnwatchAdmin.loading).prop('disabled', true);
    function fetch(type) {
      return $.get(OnwatchAdmin.ajaxurl, {
        action: 'onwatch_tmdb_fetch',
        tmdb_id: id,
        type: type
      });
    }
    fetch('movie').done(function(res) {
      if (res.success && res.data.title) {
        fillFields(res.data);
      }
    }).fail(function() {
      fetch('tv').done(function(res) {
        if (res.success && res.data.title) {
          fillFields(res.data);
        }
      }).fail(function() {
        alert(OnwatchAdmin.none);
      });
    }).always(function() {
      btn.text('Go').prop('disabled', false);
    });
  });

  function fillFields(d) {
    currentTmdbId = d.tmdb_id;
    currentType = d.type;

    $('#title').val(d.title);
    $('#content').val(d.overview);
    $('input[name="original_title"]').val(d.original_title);
    $('input[name="poster_hotlink"]').val(d.poster);
    $('input[name="backrop_hotlink"]').val(d.backdrop);
    $('input[name="duration"]').val(d.runtime);
    $('input[name="rating"]').val(d.rating);
    $('input[name="release_date"]').val(d.release_date);
    $('textarea[name="trailer"]').val(d.trailer);

    if (d.type === 'tv') {
      $('input[name="first_air_date"]').val(d.first_air_date);
      $('input[name="last_air_date"]').val(d.last_air_date);
      $('input[name="status"]').val(d.status);
      $('input[name="in_production"]').prop('checked', d.in_production);
    }

    if (d.genres && d.genres.length) {
      var $cat = $('#categorydiv input[type=checkbox]');
      $cat.each(function() {
        if (d.genres.indexOf($(this).val()) > -1) $(this).prop('checked', true);
      });
    }

    $('#tmdb_actions').show();
    $('#tmdb_import_status').text('');
    if (d.type === 'movie') {
      $('#tmdb_import_seasons, #tmdb_import_episodes, #tmdb_import_all, #tmdb_quick_links').hide();
    } else {
      $('#tmdb_import_seasons, #tmdb_import_episodes, #tmdb_import_all, #tmdb_quick_links').show();
    }
  }

  $('.onwatch-add-media').on('click', function(e) {
    e.preventDefault();
    var frame = wp.media({
      title: $(this).data('title'),
      button: { text: $(this).data('button') },
      multiple: false
    });
    frame.on('select', function() {
      var attachment = frame.state().get('selection').first().toJSON();
      $('.onwatch-add-media').html('<img src="' + attachment.url + '" style="width:100%;height:auto;">');
      $('.onwatch-media-delete').closest('p').show();
    });
    frame.open();
  });

  $('.onwatch-media-delete').on('click', function(e) {
    e.preventDefault();
    $('.onwatch-add-media').text('Set backdrop image');
    $(this).closest('p').hide();
  });

  function canImport() {
    if (OnwatchAdmin.post_id > 0) return true;
    $('#tmdb_import_status').text(OnwatchAdmin.save_first);
    return false;
  }

  $('#tmdb_import_all').on('click', function() {
    if (!currentTmdbId || !canImport()) return;
    var btn = $(this);
    btn.prop('disabled', true);
    $('#tmdb_import_status').text(OnwatchAdmin.loading);
    $.get(OnwatchAdmin.ajaxurl, {
      action: 'onwatch_import_all',
      security: OnwatchAdmin.nonce,
      tmdb_id: currentTmdbId,
      post_id: OnwatchAdmin.post_id
    }).done(function(r) {
      $('#tmdb_import_status').text(r);
    }).fail(function() {
      $('#tmdb_import_status').text(OnwatchAdmin.none);
    }).always(function() {
      btn.prop('disabled', false);
    });
  });

  $('#tmdb_import_seasons').on('click', function() {
    if (!currentTmdbId || !canImport()) return;
    var btn = $(this);
    btn.prop('disabled', true);
    $('#tmdb_import_status').text(OnwatchAdmin.loading);
    $.get(OnwatchAdmin.ajaxurl, {
      action: 'grabberseasons',
      security: OnwatchAdmin.nonce,
      timdb: currentTmdbId,
      id: OnwatchAdmin.post_id
    }).done(function(r) {
      $('#tmdb_import_status').text(r);
    }).fail(function() {
      $('#tmdb_import_status').text(OnwatchAdmin.none);
    }).always(function() {
      btn.prop('disabled', false);
    });
  });

  $('#tmdb_import_episodes').on('click', function() {
    if (!canImport()) return;
    var sn = prompt(OnwatchAdmin.prompt_season);
    if (sn === null) return;
    if (!currentTmdbId || !sn) { alert(OnwatchAdmin.none_season); return; }
    var btn = $(this);
    btn.prop('disabled', true);
    $('#tmdb_import_status').text(OnwatchAdmin.loading);
    $.get(OnwatchAdmin.ajaxurl, {
      action: 'grabberepisodes',
      security: OnwatchAdmin.nonce,
      timdb: currentTmdbId,
      id: OnwatchAdmin.post_id,
      season: sn
    }).done(function(r) {
      $('#tmdb_import_status').text(r);
    }).fail(function() {
      $('#tmdb_import_status').text(OnwatchAdmin.none);
    }).always(function() {
      btn.prop('disabled', false);
    });
  });

  $('#tmdb_quick_links').on('click', function() {
    if (!currentTmdbId) return;
    var sn = prompt(OnwatchAdmin.prompt_season);
    if (sn === null) return;
    var en = prompt(OnwatchAdmin.prompt_episode);
    if (en === null) return;
    var ls = prompt(OnwatchAdmin.prompt_links);
    if (ls === null || !ls) { alert(OnwatchAdmin.none_links); return; }
    var tt = prompt(OnwatchAdmin.prompt_type, '1');
    if (tt === null) return;
    $('#tmdb_import_status').text(OnwatchAdmin.loading);
    $.post(OnwatchAdmin.ajaxurl, {
      action: 'trgrabberlive',
      nonce: OnwatchAdmin.live_nonce,
      type: 6,
      season: sn,
      episode: en,
      links: ls,
      typel: tt,
      id: OnwatchAdmin.post_id,
      lang: 0,
      quality: 0
    }).done(function(r) {
      $('#tmdb_import_status').text(OnwatchAdmin.done);
    }).fail(function() {
      $('#tmdb_import_status').text(OnwatchAdmin.none);
    });
  });

  $('#trgrabber_quiclinks').on('click', function() {
    if (OnwatchAdmin.post_type === 'series') {
      $('#tmdb_quick_links').trigger('click');
      return;
    }
    var ls = prompt(OnwatchAdmin.prompt_links);
    if (ls === null || !ls) { alert(OnwatchAdmin.none_links); return; }
    var lines = ls.split('\n').filter(function(l) { return l.trim(); });
    if (!lines.length) return;
    for (var i = 0; i < lines.length; i++) {
      $('#tr-grabber-content-links').append(template);
      var $row = $('.TrGrabber-tblcn .tr-grabber-row:last');
      $row.find('input[name="trgrabber_link[]"]').val(lines[i].trim());
      $row.find('select[name="trgrabber_lang[]"]').append($('select[name="trgrabber_lang[]"] option').clone());
      $row.find('select[name="trgrabber_quality[]"]').append($('select[name="trgrabber_quality[]"] option').clone());
    }
  });

  /* ── Manual Seasons & Episodes (series edit page) ── */
  $('#onwatch_ms_add_season').on('click', function() {
    var post_id = OnwatchAdmin.post_id;
    if (!post_id) { alert(OnwatchAdmin.save_first); return; }
    var sn = $('#me_season_number').val();
    if (!sn) { alert(OnwatchAdmin.none_season); return; }
    $('#onwatch_ms_season_status').text(OnwatchAdmin.loading);
    $.post(OnwatchAdmin.ajaxurl, {
      action: 'onwatch_manual_add_season',
      nonce: OnwatchAdmin.manual_nonce,
      post_id: post_id,
      season_number: sn,
      season_name: $('#me_season_name').val(),
      air_date: $('#me_season_date').val(),
      overview: $('#me_season_overview').val()
    }).done(function(r) {
      if (r.success) {
        $('#onwatch_ms_season_status').text(r.data.message);
        $('#me_season_number, #me_season_name, #me_season_date, #me_season_overview').val('');
        $('#onwatch_ms_list').html(r.data.html);
        $('#me_ep_season').html('');
        $('#onwatch_ms_list .onwatch-ms-season').each(function() {
          var label = $(this).find('.onwatch-ms-season-title strong').text();
          var id = $(this).find('.onwatch-ms-delete').data('id');
          $('#me_ep_season').append('<option value="' + id + '">' + label + '</option>');
        });
      } else {
        $('#onwatch_ms_season_status').text(r.data);
      }
    }).fail(function() {
      $('#onwatch_ms_season_status').text(OnwatchAdmin.none);
    });
  });

  $('#onwatch_ms_add_episode').on('click', function() {
    var post_id = OnwatchAdmin.post_id;
    if (!post_id) { alert(OnwatchAdmin.save_first); return; }
    var season_id = $('#me_ep_season').val();
    var ep_num = $('#me_ep_number').val();
    if (!season_id || !ep_num) { alert('Fill all required fields.'); return; }
    $('#onwatch_ms_episode_status').text(OnwatchAdmin.loading);
    $.post(OnwatchAdmin.ajaxurl, {
      action: 'onwatch_manual_add_episode',
      nonce: OnwatchAdmin.manual_nonce,
      post_id: post_id,
      season_id: season_id,
      season_number: $('#me_ep_season option:selected').data('sn') || 0,
      episode_number: ep_num,
      episode_name: $('#me_ep_name').val(),
      air_date: $('#me_ep_date').val(),
      still_url: $('#me_ep_still').val(),
      overview: $('#me_ep_overview').val()
    }).done(function(r) {
      if (r.success) {
        $('#onwatch_ms_episode_status').text(r.data.message);
        $('#me_ep_number, #me_ep_name, #me_ep_date, #me_ep_still, #me_ep_overview').val('');
        $('#onwatch_ms_list').html(r.data.html);
      } else {
        $('#onwatch_ms_episode_status').text(r.data);
      }
    }).fail(function() {
      $('#onwatch_ms_episode_status').text(OnwatchAdmin.none);
    });
  });

  $(document).on('click', '.onwatch-ms-delete', function() {
    if (!confirm('Delete?')) return;
    var btn = $(this);
    $.post(OnwatchAdmin.ajaxurl, {
      action: 'onwatch_manual_delete_term',
      nonce: OnwatchAdmin.manual_nonce,
      term_id: btn.data('id'),
      taxonomy: btn.data('tax'),
      post_id: OnwatchAdmin.post_id
    }).done(function(r) {
      if (r.success) {
        $('#onwatch_ms_list').html(r.data.html);
      }
    });
  });

  /* ── Episodes page: Add Episode ── */
  $(document).on('click', '.onwatch-ep-add-btn', function() {
    var $form = $(this).closest('.onwatch-add-ep-form');
    var series_id = $form.data('series');
    var sn = $form.find('.ep-season-num').val();
    var en = $form.find('.ep-episode-num').val();
    var name = $form.find('.ep-name').val();
    if (!sn || !en) { alert('Fill season and episode numbers.'); return; }
    var $status = $form.find('.onwatch-ep-add-status');
    $status.text(OnwatchAdmin.loading);
    $.post(OnwatchAdmin.ajaxurl, {
      action: 'onwatch_manual_add_episode',
      nonce: OnwatchAdmin.manual_nonce,
      post_id: series_id,
      season_id: 0,
      season_number: sn,
      episode_number: en,
      episode_name: name,
      air_date: $form.find('.ep-date').val(),
      still_url: $form.find('.ep-still').val(),
      overview: $form.find('.ep-overview').val()
    }).done(function(r) {
      if (r.success) {
        $status.text(r.data.message);
        $form.find('input, textarea').val('');
        $form.find('.ep-episode-num').val(parseInt(en) + 1);
        setTimeout(function() { location.reload(); }, 800);
      } else {
        $status.text(r.data);
      }
    }).fail(function() {
      $status.text(OnwatchAdmin.none);
    });
  });
});
