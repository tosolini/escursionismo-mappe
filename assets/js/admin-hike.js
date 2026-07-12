(function ($) {
  'use strict';

  if (typeof window.emAdminHikeData === 'undefined') return;

  var D = window.emAdminHikeData;
  var restUrl = D.restUrl, wpRestUrl = D.wpRestUrl, nonce = D.nonce;
  var hikeId = D.hikeId;
  var gpxUrl = D.gpxUrl || '', gpxFileId = D.gpxFileId || 0;
  var pois = D.pois || [], icons = D.icons || {};
  var basemaps = D.basemaps || {}, defaultBasemap = D.basemap || 'OpenStreetMap';
  var mapContainerId = D.containerId || 'em-editor-map';
  var poiListId = D.poiListId || 'em-poi-list';

  var hasUnsaved = false;
  var saveTimeout = null;

  var map, gpxLayer = null, poiMarkers = {}, poiRecords = {};
  var poiEditId = null;
  var activeIconType = 'marker';
  var tileLayers = {};

  var dom = {};

  function init() {
    cacheDom();
    if (!dom.mapContainer) return;
    initMap();
    initGpx();
    initPois();
    initPoiIconGrid();
    initEvents();
    updatePoiList();
    updateSaveStatus();
  }

  function cacheDom() {
    dom.editor = document.getElementById('em-admin-editor');
    dom.mapContainer = document.getElementById(mapContainerId);
    dom.poiList = document.getElementById(poiListId);
    dom.titleInput = document.getElementById('em-post-title');
    dom.contentTextarea = document.getElementById('em-post-content');
    dom.saveBtn = document.getElementById('em-save-btn');
    dom.saveStatus = document.getElementById('em-save-status');
    dom.statusSelect = document.getElementById('em-post-status');
    dom.statusBadge = document.querySelector('.em-status-badge');
    dom.basemapSelect = document.getElementById('em-basemap');
    dom.poiFormArea = document.getElementById('em-poi-form-area');
    dom.poiTitleInput = document.getElementById('em-poi-title-input');
    dom.poiSuggestions = document.getElementById('em-poi-suggestions');
    dom.poiDescInput = document.getElementById('em-poi-desc-input');
    dom.poiCoords = document.getElementById('em-poi-coords');
    dom.poiLatInput = document.getElementById('em-poi-lat');
    dom.poiLonInput = document.getElementById('em-poi-lon');
    dom.poiEditId = document.getElementById('em-poi-edit-id');
    dom.poiSaveBtn = document.getElementById('em-poi-save-btn');
    dom.poiDeleteBtn = document.getElementById('em-poi-delete-btn');
    dom.poiCancelBtn = document.getElementById('em-poi-cancel-btn');
    dom.poiFormTitle = document.getElementById('em-poi-form-title');
    dom.poiFormClose = document.getElementById('em-poi-form-close');
    dom.poiCountBadge = document.getElementById('em-poi-count-badge');
    dom.poiIconGrid = document.getElementById('em-poi-icon-grid');
    dom.gpxDropzone = document.getElementById('em-gpx-dropzone');
    dom.gpxFileInput = document.getElementById('em-gpx-file-input');
    dom.gpxSelectBtn = document.getElementById('em-gpx-select-btn');
    dom.gpxMediaBtn = document.getElementById('em-gpx-media-btn');
    dom.gpxUrlInput = document.getElementById('em-gpx-url');
    dom.gpxFileIdInput = document.getElementById('em-gpx-file-id');
    dom.gpxInfo = document.getElementById('em-gpx-info');
    dom.gpxFilenameText = document.getElementById('em-gpx-filename-text');
    dom.gpxStats = document.getElementById('em-gpx-stats');
    dom.gpxRemoveBtn = document.getElementById('em-gpx-remove-btn');
    dom.gpxProgress = dom.gpxDropzone ? dom.gpxDropzone.querySelector('.em-gpx-progress') : null;
    dom.saveForm = document.getElementById('em-save-form');
    dom.formTitle = document.getElementById('em-form-title');
    dom.formContent = document.getElementById('em-form-content');
    dom.formStatus = document.getElementById('em-form-status');
    dom.formBasemap = document.getElementById('em-form-basemap');
    dom.formGpxUrl = document.getElementById('em-form-gpx-url');
    dom.zoomDisplay = document.getElementById('em-zoom-level');
    dom.statDist = document.getElementById('em-stat-dist');
    dom.statEle = document.getElementById('em-stat-ele');
    dom.statMaxEle = document.getElementById('em-stat-maxele');
  }

  function initMap() {
    map = L.map(dom.mapContainer, {
      center: [46.3, 13.0],
      zoom: 10,
      scrollWheelZoom: true,
      zoomControl: true,
    });

    var baseLayers = {};
    for (var k in basemaps) {
      var def = basemaps[k];
      var tl = L.tileLayer(def.url, {
        attribution: def.attribution,
        maxZoom: def.maxZoom || 19,
      });
      tileLayers[k] = tl;
      baseLayers[def.label || k] = tl;
      if (k === defaultBasemap) tl.addTo(map);
    }
    if (!tileLayers[defaultBasemap]) {
      var firstKey = Object.keys(tileLayers)[0];
      if (firstKey && tileLayers[firstKey]) tileLayers[firstKey].addTo(map);
    }
    L.control.layers(baseLayers, null, { collapsed: true }).addTo(map);

    map.on('zoomend', function () {
      if (dom.zoomDisplay) dom.zoomDisplay.textContent = map.getZoom();
    });
    if (dom.zoomDisplay) dom.zoomDisplay.textContent = map.getZoom();

    map.on('click', onMapClick);

    setTimeout(function () { map.invalidateSize(); }, 100);
    setTimeout(function () { map.invalidateSize(); }, 500);
  }

  function initGpx() {
    if (!gpxUrl) return;
    loadGpxTrack(gpxUrl);
  }

  function loadGpxTrack(url) {
    if (gpxLayer) {
      map.removeLayer(gpxLayer);
      gpxLayer = null;
    }
    if (!url) return;
    gpxLayer = new L.GPX(url, {
      async: true,
      marker_options: { startIconUrl: false, endIconUrl: false, wptIconUrls: {} },
      polyline_options: { color: '#2563eb', weight: 3, opacity: 0.8 },
    }).on('loaded', function (e) {
      var b = e.target.getBounds();
      if (b.isValid()) map.fitBounds(b, { padding: [30, 30], maxZoom: 14 });
      map.invalidateSize();
    }).on('error', function () {});
    gpxLayer.addTo(map);
  }

  function initPois() {
    pois.forEach(function (p) { addPoiToMap(p, false); });
  }

  function initPoiIconGrid() {
    if (!dom.poiIconGrid) return;
    var h = '';
    var cats = {};
    for (var k in icons) {
      var c = icons[k];
      var cat = c.category || 'Generico';
      if (!cats[cat]) cats[cat] = [];
      cats[cat].push({ key: k, icon: c.icon, color: c.color, label: c.label });
    }
    h += '<div class="em-icon-categories">';
    var first = true;
    for (var catName in cats) {
      var catKey = catName.replace(/\s+/g, '-').toLowerCase();
      var items = cats[catName];
      h += '<div class="em-icon-category">';
      h += '<button type="button" class="em-icon-cat-toggle' + (first ? ' active' : '') + '" data-cat="' + catKey + '">' + escapeHtml(catName) + ' (' + items.length + ')</button>';
      h += '<div class="em-icon-items' + (first ? ' open' : '') + '" data-cat="' + catKey + '">';
      items.forEach(function (item) {
        h += '<button type="button" class="em-icon-item' + (item.key === 'marker' ? ' selected' : '') + '" data-icon="' + item.key + '" title="' + escapeHtml(item.label) + '" style="--icon-color:' + item.color + '">';
        h += '<i class="fa-solid ' + item.icon + '"></i>';
        h += '<span class="em-icon-label">' + escapeHtml(item.label) + '</span>';
        h += '</button>';
      });
      h += '</div></div>';
      first = false;
    }
    h += '</div>';
    dom.poiIconGrid.innerHTML = h;

    dom.poiIconGrid.querySelectorAll('.em-icon-cat-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cat = this.getAttribute('data-cat');
        var items = dom.poiIconGrid.querySelector('.em-icon-items[data-cat="' + cat + '"]');
        var isOpen = items.classList.contains('open');
        items.classList.toggle('open');
        this.classList.toggle('active');
      });
    });

    dom.poiIconGrid.querySelectorAll('.em-icon-item').forEach(function (item) {
      item.addEventListener('click', function () {
        dom.poiIconGrid.querySelectorAll('.em-icon-item').forEach(function (el) { el.classList.remove('selected'); });
        this.classList.add('selected');
        activeIconType = this.getAttribute('data-icon');
      });
    });
  }

  function initEvents() {
    if (dom.saveBtn) dom.saveBtn.addEventListener('click', onSave);

    if (dom.titleInput) {
      dom.titleInput.addEventListener('input', function () { markUnsaved(); });
    }
    if (dom.contentTextarea) {
      dom.contentTextarea.addEventListener('input', function () { markUnsaved(); });
    }
    if (dom.statusSelect) {
      dom.statusSelect.addEventListener('change', function () {
        var val = this.value;
        if (dom.statusBadge) {
          dom.statusBadge.textContent = this.options[this.selectedIndex].text;
          dom.statusBadge.className = 'em-status-badge em-status-' + val;
        }
        markUnsaved();
      });
    }
    if (dom.basemapSelect) {
      dom.basemapSelect.addEventListener('change', function () {
        switchBasemap(this.value);
        markUnsaved();
      });
    }

    if (dom.poiSaveBtn) dom.poiSaveBtn.addEventListener('click', onPoiSave);
    if (dom.poiCancelBtn) dom.poiCancelBtn.addEventListener('click', hidePoiForm);
    if (dom.poiDeleteBtn) dom.poiDeleteBtn.addEventListener('click', onPoiDelete);
    if (dom.poiFormClose) dom.poiFormClose.addEventListener('click', hidePoiForm);

    if (dom.poiTitleInput) {
      dom.poiTitleInput.addEventListener('input', debounce(onPoiTitleInput, 300));
      dom.poiTitleInput.addEventListener('focus', function () {
        if (dom.poiSuggestions && dom.poiSuggestions.children.length > 0) {
          dom.poiSuggestions.style.display = 'block';
        }
      });
      document.addEventListener('click', function (e) {
        if (dom.poiSuggestions && !e.target.closest('.em-autocomplete-wrap')) {
          dom.poiSuggestions.style.display = 'none';
        }
      });
    }

    if (dom.gpxDropzone) {
      dom.gpxDropzone.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.classList.add('dragover');
      });
      dom.gpxDropzone.addEventListener('dragleave', function () {
        this.classList.remove('dragover');
      });
      dom.gpxDropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        this.classList.remove('dragover');
        var files = e.dataTransfer.files;
        if (files.length > 0) uploadGpxFile(files[0]);
      });
      dom.gpxDropzone.addEventListener('click', function () {
        if (dom.gpxFileInput) dom.gpxFileInput.click();
      });
    }
    if (dom.gpxSelectBtn) {
      dom.gpxSelectBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (dom.gpxFileInput) dom.gpxFileInput.click();
      });
    }
    if (dom.gpxFileInput) {
      dom.gpxFileInput.addEventListener('change', function () {
        if (this.files.length > 0) uploadGpxFile(this.files[0]);
      });
    }
    if (dom.gpxMediaBtn) {
      dom.gpxMediaBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        openMediaLibrary();
      });
    }
    if (dom.gpxRemoveBtn) {
      dom.gpxRemoveBtn.addEventListener('click', removeGpx);
    }

    window.addEventListener('beforeunload', function (e) {
      if (hasUnsaved) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    dom.editor.querySelectorAll('.em-collapse-toggle').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var targetId = this.getAttribute('data-target');
        if (!targetId) return;
        var target = document.getElementById(targetId);
        if (!target) return;
        target.classList.toggle('collapsed');
        var icon = this.querySelector('.dashicons');
        if (icon) icon.classList.toggle('dashicons-arrow-up-alt2');
        if (icon) icon.classList.toggle('dashicons-arrow-down-alt2');
      });
    });
  }

  function switchBasemap(key) {
    for (var k in tileLayers) {
      if (map.hasLayer(tileLayers[k])) {
        map.removeLayer(tileLayers[k]);
      }
    }
    if (tileLayers[key]) {
      map.addLayer(tileLayers[key]);
    }
    defaultBasemap = key;
  }

  function onMapClick(e) {
    var lat = e.latlng.lat, lng = e.latlng.lng;
    showPoiForm(null, lat, lng);
  }

  function showPoiForm(poi, lat, lon) {
    if (!dom.poiFormArea) return;
    dom.poiFormArea.style.display = 'block';
    dom.poiEditId.value = poi ? poi.id : '';
    dom.poiTitleInput.value = poi ? poi.title : '';
    dom.poiDescInput.value = poi ? (poi.content || '') : '';
    dom.poiLatInput.value = poi ? poi.lat : lat;
    dom.poiLonInput.value = poi ? poi.lon : lon;
    dom.poiCoords.textContent = (poi ? poi.lat : lat).toFixed(6) + ', ' + (poi ? poi.lon : lon).toFixed(6);

    if (poi) {
      dom.poiFormTitle.textContent = 'Modifica POI';
      dom.poiSaveBtn.textContent = 'Salva POI';
      dom.poiDeleteBtn.style.display = '';
      poiEditId = poi.id;
      activeIconType = poi.icon_type || 'marker';
    } else {
      dom.poiFormTitle.textContent = 'Nuovo POI';
      dom.poiSaveBtn.textContent = 'Aggiungi POI';
      dom.poiDeleteBtn.style.display = 'none';
      poiEditId = null;
      activeIconType = 'marker';
    }

    var iconItems = dom.poiIconGrid.querySelectorAll('.em-icon-item');
    iconItems.forEach(function (el) {
      el.classList.toggle('selected', el.getAttribute('data-icon') === activeIconType);
    });

    dom.poiSuggestions.style.display = 'none';
    dom.poiTitleInput.focus();
  }

  function hidePoiForm() {
    if (!dom.poiFormArea) return;
    dom.poiFormArea.style.display = 'none';
    poiEditId = null;
    dom.poiEditId.value = '';
    dom.poiTitleInput.value = '';
    dom.poiDescInput.value = '';
    dom.poiSuggestions.innerHTML = '';
    dom.poiSuggestions.style.display = 'none';
  }

  function onPoiTitleInput() {
    var val = dom.poiTitleInput.value.trim();
    if (val.length < 2) {
      dom.poiSuggestions.style.display = 'none';
      return;
    }
    apiFetch('/pois/search?s=' + encodeURIComponent(val) + '&exclude_hike=' + hikeId)
      .then(function (results) {
        if (!results || results.length === 0) {
          dom.poiSuggestions.style.display = 'none';
          return;
        }
        var h = '';
        results.forEach(function (r) {
          h += '<button type="button" class="em-suggestion-item" data-id="' + r.id + '" data-lat="' + r.lat + '" data-lon="' + r.lon + '" data-icon="' + (r.icon_type || 'marker') + '" data-title="' + escapeHtml(r.title) + '">';
          h += '<span class="em-suggestion-icon" style="color:' + (r.color || '#e74c3c') + '"><i class="fa-solid ' + (r.icon || 'fa-location-dot') + '"></i></span> ';
          h += '<span class="em-suggestion-name">' + escapeHtml(r.title) + '</span>';
          h += '<span class="em-suggestion-link">Collega</span>';
          h += '</button>';
        });
        dom.poiSuggestions.innerHTML = h;
        dom.poiSuggestions.style.display = 'block';

        dom.poiSuggestions.querySelectorAll('.em-suggestion-item').forEach(function (item) {
          item.addEventListener('click', function () {
            var id = parseInt(this.getAttribute('data-id'));
            apiFetch('/pois/' + id + '/link', 'POST', { hike_id: hikeId })
              .then(function () {
                var lat = parseFloat(this.getAttribute('data-lat'));
                var lon = parseFloat(this.getAttribute('data-lon'));
                var iconType = this.getAttribute('data-icon');
                var title = this.getAttribute('data-title');
                addPoiToMap({ id: id, title: title, lat: lat, lon: lon, icon_type: iconType }, true);
                hidePoiForm();
              }.bind(this))
              .catch(function (err) { logErr('Link POI: ' + err.message); });
          });
        });
      })
      .catch(function () {});
  }

  function onPoiSave() {
    var title = dom.poiTitleInput.value.trim();
    if (!title) { alert('Inserisci un nome per il POI'); return; }
    var desc = dom.poiDescInput.value.trim();
    var lat = parseFloat(dom.poiLatInput.value);
    var lon = parseFloat(dom.poiLonInput.value);

    if (poiEditId) {
      apiFetch('/pois/' + poiEditId, 'PUT', {
        title: title,
        lat: lat,
        lon: lon,
        icon_type: activeIconType,
        content: desc,
      }).then(function (data) {
        var marker = poiMarkers[poiEditId];
        if (marker) {
          marker.setIcon(createPoiIcon(activeIconType));
        }
        if (poiRecords[poiEditId]) {
          poiRecords[poiEditId].title = title;
          poiRecords[poiEditId].icon_type = activeIconType;
          poiRecords[poiEditId].lat = lat;
          poiRecords[poiEditId].lon = lon;
        }
        hidePoiForm();
        updatePoiList();
        markUnsaved();
      }).catch(function (err) { alert('Errore salvataggio POI'); logErr('Save POI: ' + err.message); });
    } else {
      apiFetch('/pois', 'POST', {
        title: title,
        lat: lat,
        lon: lon,
        icon_type: activeIconType,
        content: desc,
        hike_id: hikeId,
      }).then(function (created) {
        created.icon_type = activeIconType;
        addPoiToMap(created, true);
        hidePoiForm();
        markUnsaved();
      }).catch(function (err) { alert('Errore creazione POI'); logErr('Create POI: ' + err.message); });
    }
  }

  function onPoiDelete() {
    var id = parseInt(dom.poiEditId.value);
    if (!id) return;
    var name = dom.poiTitleInput.value.trim() || 'questo POI';
    if (!confirm('Rimuovere "' + name + '" da questa escursione?')) return;
    apiFetch('/pois/' + id + '/link?hike_id=' + hikeId, 'DELETE')
      .then(function () {
        if (poiMarkers[id]) { map.removeLayer(poiMarkers[id]); delete poiMarkers[id]; }
        delete poiRecords[id];
        hidePoiForm();
        updatePoiList();
        markUnsaved();
      })
      .catch(function (err) { alert('Errore rimozione POI'); logErr('Delete POI: ' + err.message); });
  }

  function createPoiIcon(iconType) {
    var info = icons[iconType] || icons.marker || { icon: 'fa-location-dot', color: '#e74c3c' };
    return L.divIcon({
      className: 'em-custom-marker',
      html: '<div style="background:' + info.color + ';width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);color:#fff;font-size:14px"><i class="fa-solid ' + info.icon + '"></i></div>',
      iconSize: [32, 32],
      iconAnchor: [16, 16],
      popupAnchor: [0, -18],
    });
  }

  function addPoiToMap(poi, animate) {
    var id = poi.id;
    if (poiMarkers[id]) { map.removeLayer(poiMarkers[id]); delete poiMarkers[id]; }

    var lat = parseFloat(poi.lat);
    var lon = parseFloat(poi.lon);
    var iconType = poi.icon_type || 'marker';

    var marker = L.marker([lat, lon], {
      icon: createPoiIcon(iconType),
      draggable: true,
    }).addTo(map);

    marker.on('dragend', function () {
      var ll = marker.getLatLng();
      apiFetch('/pois/' + id + '/link', 'POST', { hike_id: hikeId }).catch(function () {});
      apiFetch('/pois/' + id, 'PUT', { lat: ll.lat, lon: ll.lng }).catch(function () {});
    });

    marker.on('click', function () {
      var rec = poiRecords[id] || { id: id, title: poi.title, icon_type: iconType, lat: lat, lon: lon, content: poi.content || '' };
      showPoiForm(rec, null, null);
    });

    poiMarkers[id] = marker;
    poiRecords[id] = { id: id, title: poi.title, icon_type: iconType, lat: lat, lon: lon, content: poi.content || '' };

    updatePoiList();

    if (animate) {
      map.panTo([lat, lon]);
      setTimeout(function () {
        marker.openPopup();
        var popupContent = '<div class="em-poi-popup-simple"><strong>' + escapeHtml(poi.title) + '</strong></div>';
        var info = icons[iconType] || icons.marker || {};
        popupContent = '<div class="em-poi-popup-simple"><span style="color:' + (info.color || '#e74c3c') + '"><i class="fa-solid ' + (info.icon || 'fa-location-dot') + '"></i></span> <strong>' + escapeHtml(poi.title) + '</strong></div>';
        marker.bindPopup(popupContent).openPopup();
        setTimeout(function () { marker.closePopup(); }, 2000);
      }, 400);
    }
  }

  function updatePoiList() {
    if (!dom.poiList) return;
    var ids = Object.keys(poiMarkers);
    dom.poiCountBadge.textContent = ids.length;

    if (ids.length === 0) {
      dom.poiList.innerHTML = '<p class="em-poi-empty">Nessun POI. Clicca sulla mappa per aggiungerne uno.</p>';
      return;
    }

    var h = '';
    ids.forEach(function (id) {
      var marker = poiMarkers[id];
      var ll = marker.getLatLng();
      var rec = poiRecords[id] || {};
      var info = icons[rec.icon_type] || icons.marker || { icon: 'fa-location-dot', color: '#e74c3c', label: 'POI' };
      h += '<div class="em-poi-list-item" data-id="' + id + '">';
      h += '<span class="em-poi-list-icon" style="color:' + info.color + '"><i class="fa-solid ' + info.icon + '"></i></span>';
      h += '<span class="em-poi-list-title">' + escapeHtml(rec.title || 'POI #' + id) + '</span>';
      h += '<button type="button" class="button button-small em-poi-list-focus" title="Centra sulla mappa">&rarr;</button>';
      h += '</div>';
    });

    dom.poiList.innerHTML = h;

    dom.poiList.querySelectorAll('.em-poi-list-item').forEach(function (item) {
      item.addEventListener('click', function () {
        var id = parseInt(this.getAttribute('data-id'));
        var marker = poiMarkers[id];
        if (marker) {
          var ll = marker.getLatLng();
          map.panTo([ll.lat, ll.lng]);
          map.setZoom(Math.max(map.getZoom(), 15));
        }
      });
      item.querySelector('.em-poi-list-focus')?.addEventListener('click', function (e) {
        e.stopPropagation();
        var id = parseInt(this.closest('.em-poi-list-item').getAttribute('data-id'));
        var marker = poiMarkers[id];
        if (marker) {
          var ll = marker.getLatLng();
          map.panTo([ll.lat, ll.lng]);
          map.setZoom(Math.max(map.getZoom(), 15));
        }
      });
    });
  }

  function uploadGpxFile(file) {
    if (!file.name.toLowerCase().endsWith('.gpx')) {
      alert('Il file deve essere in formato GPX.');
      return;
    }

    if (dom.gpxProgress) dom.gpxProgress.style.display = 'block';
    var formData = new FormData();
    formData.append('file', file);

    fetch(restUrl + '/gpx/upload', {
      method: 'POST',
      headers: {
        'X-WP-Nonce': nonce,
      },
      body: formData,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.code) throw new Error(data.message || 'Upload fallito');
      if (dom.gpxProgress) dom.gpxProgress.style.display = 'none';
      gpxUrl = data.url;
      gpxFileId = data.id;
      dom.gpxUrlInput.value = data.url;
      dom.gpxFileIdInput.value = data.id;
      dom.gpxDropzone.classList.add('has-gpx');
      dom.gpxFilenameText.textContent = data.filename;
      dom.gpxInfo.style.display = '';
      loadGpxTrack(data.url);
      if (data.stats && data.stats.distance_km) {
        if (dom.statDist) dom.statDist.textContent = data.stats.distance_km.toFixed(1) + ' km';
        if (dom.statEle) dom.statEle.textContent = data.stats.elevation_gain + ' m';
        if (dom.statMaxEle) dom.statMaxEle.textContent = data.stats.elevation_max + ' m';
        dom.gpxStats.style.display = '';
      }
      markUnsaved();
    })
    .catch(function (err) {
      if (dom.gpxProgress) dom.gpxProgress.style.display = 'none';
      alert('Errore upload GPX: ' + err.message);
      logErr('GPX upload: ' + err.message);
    });
  }

  function openMediaLibrary() {
    if (typeof wp === 'undefined' || !wp.media) {
      alert('Libreria media non disponibile.');
      return;
    }
    var frame = wp.media({
      title: 'Seleziona file GPX',
      library: { type: 'application/gpx+xml' },
      multiple: false,
      button: { text: 'Usa questo file' },
    });
    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      gpxUrl = attachment.url;
      gpxFileId = attachment.id;
      dom.gpxUrlInput.value = attachment.url;
      dom.gpxFileIdInput.value = attachment.id;
      dom.gpxDropzone.classList.add('has-gpx');
      dom.gpxFilenameText.textContent = attachment.filename;
      dom.gpxInfo.style.display = '';
      loadGpxTrack(attachment.url);
      markUnsaved();
    });
    frame.open();
  }

  function removeGpx() {
    gpxUrl = '';
    gpxFileId = 0;
    dom.gpxUrlInput.value = '';
    dom.gpxFileIdInput.value = '0';
    dom.gpxDropzone.classList.remove('has-gpx');
    dom.gpxInfo.style.display = 'none';
    dom.gpxStats.style.display = 'none';
    if (dom.gpxProgress) dom.gpxProgress.style.display = 'none';
    if (gpxLayer) { map.removeLayer(gpxLayer); gpxLayer = null; }
    markUnsaved();
  }

  function onSave() {
    dom.formTitle.value = dom.titleInput.value;
    dom.formContent.value = dom.contentTextarea.value;
    dom.formStatus.value = dom.statusSelect.value;
    dom.formBasemap.value = dom.basemapSelect.value;
    dom.formGpxUrl.value = dom.gpxUrlInput.value;

    var btn = dom.saveBtn;
    var spinner = btn ? btn.querySelector('.spinner') : null;
    if (spinner) spinner.style.display = 'inline-block';
    if (btn) btn.disabled = true;

    dom.saveForm.submit();
  }

  function markUnsaved() {
    if (!hasUnsaved) {
      hasUnsaved = true;
      updateSaveStatus();
    }
    if (saveTimeout) clearTimeout(saveTimeout);
    saveTimeout = setTimeout(function () {
      autoSave();
    }, 5000);
  }

  function autoSave() {
    if (!hasUnsaved) return;
    var title = dom.titleInput.value;
    var content = dom.contentTextarea.value;
    var status = dom.statusSelect.value;

    fetch(wpRestUrl + '/hikes/' + hikeId, {
      method: 'PUT',
      headers: {
        'X-WP-Nonce': nonce,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        title: title,
        content: content,
        status: status,
      }),
    })
    .then(function (r) {
      if (!r.ok) throw new Error('Auto-save fallito');
      return r.json();
    })
    .then(function () {
      hasUnsaved = false;
      updateSaveStatus();
    })
    .catch(function () {});
  }

  function updateSaveStatus() {
    if (!dom.saveStatus) return;
    if (hasUnsaved) {
      dom.saveStatus.textContent = 'Modifiche non salvate';
      dom.saveStatus.className = 'em-save-status unsaved';
    } else {
      dom.saveStatus.textContent = 'Salvato';
      dom.saveStatus.className = 'em-save-status saved';
    }
  }

  function apiFetch(path, method, body) {
    var opts = { headers: { 'X-WP-Nonce': nonce } };
    if (body) { opts.body = JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
    return fetch(restUrl + path, { method: method || 'GET', headers: opts.headers, body: opts.body })
      .then(function (r) {
        if (!r.ok) throw new Error(r.status + ' ' + r.statusText);
        return r.json();
      });
  }

  function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function debounce(fn, delay) {
    var timer = null;
    return function () {
      var ctx = this, args = arguments;
      if (timer) clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
  }

  function logErr(m) { if (window.console) console.error('[EM]', m); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(jQuery);
