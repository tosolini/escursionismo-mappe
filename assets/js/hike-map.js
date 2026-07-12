(function($) {
    'use strict';

    var elevationCharts = {};

    function addResetControl(map, getBoundsFn) {
        var ResetControl = L.Control.extend({
            options: { position: 'topleft' },
            onAdd: function() {
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                container.style.backgroundColor = '#fff';
                container.style.cursor = 'pointer';
                container.innerHTML = '<a href="#" style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;font-size:16px;line-height:30px;" title="Centra mappa"><i class="fa-solid fa-house"></i></a>';
                container.title = 'Centra mappa';
                L.DomEvent.on(container, 'click', function(e) {
                    L.DomEvent.stopPropagation(e);
                    L.DomEvent.preventDefault(e);
                    var bounds = getBoundsFn();
                    if (bounds && bounds.isValid()) {
                        map.fitBounds(bounds, { padding: [20, 20], animate: true });
                    }
                });
                return container;
            },
        });
        map.addControl(new ResetControl());
    }

    function eleColor(ele, minEle, maxEle) {
        var t = maxEle > minEle ? (ele - minEle) / (maxEle - minEle) : 0.5;
        t = Math.max(0, Math.min(1, t));
        var r, g, b;
        if (t < 0.33) {
            var s = t / 0.33;
            r = Math.round(34 + (132 - 34) * s);
            g = Math.round(197 - (197 - 187) * s);
            b = Math.round(94 - (94 - 43) * s);
        } else if (t < 0.66) {
            var s = (t - 0.33) / 0.33;
            r = Math.round(132 + (249 - 132) * s);
            g = Math.round(187 - (187 - 115) * s);
            b = Math.round(43 - (43 - 22) * s);
        } else {
            var s = (t - 0.66) / 0.34;
            r = Math.round(249 - (249 - 239) * s);
            g = Math.round(115 - (115 - 68) * s);
            b = Math.round(22 - (22 - 36) * s);
        }
        return 'rgb(' + r + ',' + g + ',' + b + ')';
    }

    window.initEMMap = function(mapId, data) {
        var container = document.getElementById(mapId);
        if (!container) return;

        var wrapper = container.closest('.em-map-wrapper');
        var loading = wrapper ? wrapper.querySelector('.em-map-loading') : null;

        var map = L.map(mapId, {
            center: [46.3, 13.0],
            zoom: data.zoom || 10,
            scrollWheelZoom: true,
        });

        var defaultOSM = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        });

        var baseLayers = {}, activeLayer = null;
        var bmDefs = data.basemaps;
        if (!bmDefs || Object.keys(bmDefs).length === 0) {
            bmDefs = {
                OpenStreetMap: { url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', attribution: '&copy; OpenStreetMap', maxZoom: 19, label: 'OpenStreetMap' },
            };
            data.basemap = 'OpenStreetMap';
        }
        var bmKey = data.basemap || 'OpenStreetMap';
        for (var k in bmDefs) {
            var def = bmDefs[k];
            var tl = L.tileLayer(def.url, { attribution: def.attribution, maxZoom: def.maxZoom || 19 });
            baseLayers[def.label || k] = tl;
            if (k === bmKey) activeLayer = tl;
        }
        if (!activeLayer) {
            for (var k2 in baseLayers) { activeLayer = baseLayers[k2]; break; }
        }
        if (activeLayer) activeLayer.addTo(map);
        L.control.layers(baseLayers, null, { collapsed: false }).addTo(map);

        L.control.minimap(defaultOSM, { position: 'bottomright' }).addTo(map);

        var trackBounds = null;
        addResetControl(map, function() { return trackBounds; });

        if (window.console) console.log('[EM] basemaps:', Object.keys(bmDefs).length, 'active:', (activeLayer ? 'yes' : 'no'), 'data.basemap:', data.basemap);

        var profile = data.elevationProfile;
        var minEle = Infinity, maxEle = -Infinity;
        if (profile && profile.length > 1) {
            profile.forEach(function(p) {
                if (p.e < minEle) minEle = p.e;
                if (p.e > maxEle) maxEle = p.e;
            });
        }

        function onMapReady() {
            if (loading) loading.classList.add('em-loaded');
            if (profile && profile.length > 1) {
                renderElevationChart(mapId, data, map);
            }
        }

        if (data.gpxUrl) {
            var gpxLayer = new L.GPX(data.gpxUrl, {
                async: true,
                marker_options: {
                    startIconUrl: false,
                    endIconUrl: false,
                    wptIconUrls: {},
                },
                polyline_options: {
                    color: '#2563eb',
                    weight: 3,
                    opacity: 0.8,
                },
            }).on('loaded', function(e) {
                var bounds = e.target.getBounds();
                if (bounds.isValid()) {
                    trackBounds = bounds;
                    map.fitBounds(bounds, { padding: [20, 20] });
                }
                drawColoredTrack(e.target, map, profile, minEle, maxEle);
                onMapReady();
            }).on('error', function() {
                onMapReady();
            });

            gpxLayer.addTo(map);
        } else {
            onMapReady();
        }

        if (data.pois && data.pois.length > 0) {
            addPoisToMap(map, data.pois, data.cluster);
        }

        map._emProfile = profile;
        map._emHoverMarker = null;
    };

    function drawColoredTrack(gpxLayer, map, profile, minEle, maxEle) {
        if (!profile || profile.length < 2) return;

        gpxLayer.eachLayer(function(group) {
            if (group instanceof L.FeatureGroup || group instanceof L.LayerGroup) {
                var toRemove = [];
                group.eachLayer(function(layer) {
                    if (layer instanceof L.Polyline) {
                        toRemove.push(layer);
                    }
                });
                toRemove.forEach(function(layer) {
                    group.removeLayer(layer);
                });
            }
        });

        var colored = L.featureGroup();
        profile.forEach(function(p, i) {
            if (i === 0) return;
            var pPrev = profile[i - 1];
            if (!pPrev.lat || !p.lat) return;
            var midEle = (pPrev.e + p.e) / 2;
            var color = eleColor(midEle, minEle, maxEle);
            var seg = L.polyline([
                [pPrev.lat, pPrev.lon],
                [p.lat, p.lon]
            ], { color: color, weight: 3.5, opacity: 0.9 });
            colored.addLayer(seg);
        });

        colored.addTo(map);
        map._emColoredTrack = colored;
    }

    function renderElevationChart(mapId, data, map) {
        if (typeof Chart === 'undefined') return;

        var chartId = mapId + '-chart';

        if (elevationCharts[mapId]) {
            elevationCharts[mapId].destroy();
            delete elevationCharts[mapId];
        }

        var canvas = document.getElementById(chartId);
        if (!canvas) return;

        canvas.width = canvas.clientWidth || 600;
        canvas.height = canvas.clientHeight || 180;

        var profile = data.elevationProfile;
        if (!profile || profile.length < 2) return;

        var labels = profile.map(function(p) { return p.d; });
        var values = profile.map(function(p) { return p.e; });

        var ctx = canvas.getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, 180);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.20)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.01)');

        var wrap = document.getElementById(chartId + '-wrap');
        var crossLine = document.createElement('div');
        crossLine.className = 'em-chart-crosshair';
        crossLine.style.display = 'none';
        if (wrap) wrap.appendChild(crossLine);

        elevationCharts[mapId] = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Altitudine (m)',
                    data: values,
                    borderColor: '#2563eb',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHitRadius: 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(ctx2) {
                                return ctx2.parsed.y + ' m';
                            },
                            title: function(ctx2) {
                                return ctx2[0].label + ' km';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Distanza (km)', font: { size: 11 } },
                        grid: { display: false },
                        ticks: { font: { size: 10 } },
                    },
                    y: {
                        title: { display: true, text: 'Altitudine (m)', font: { size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.06)' },
                        ticks: { font: { size: 10 } },
                        beginAtZero: false,
                    },
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                onHover: function(event) {
                    var wrapRect = wrap.getBoundingClientRect();
                    var rect = canvas.getBoundingClientRect();
                    var cssX = event.x;
                    var chartArea = elevationCharts[mapId].chartArea;

                    if (crossLine && chartArea) {
                        if (cssX >= chartArea.left && cssX <= chartArea.right) {
                            crossLine.style.display = 'block';
                            crossLine.style.left = (cssX + rect.left - wrapRect.left) + 'px';
                        } else {
                            crossLine.style.display = 'none';
                        }
                    }

                    var meta = elevationCharts[mapId].getDatasetMeta(0);
                    if (!meta || !meta.data || meta.data.length === 0) return;
                    var closest = null;
                    var minDist = Infinity;
                    meta.data.forEach(function(point, i) {
                        var d = Math.abs(point.x - cssX);
                        if (d < minDist) { minDist = d; closest = i; }
                    });
                    if (closest !== null && profile[closest]) {
                        updateMapHover(map, profile[closest]);
                    }
                },
            },
        });

        if (wrap) {
            wrap.addEventListener('mouseleave', function() {
                clearMapHover(map);
                if (crossLine) crossLine.style.display = 'none';
            });
        }
    }

    function updateMapHover(map, point) {
        if (!map || !point || !point.lat || !point.lon) return;

        var latlng = L.latLng(point.lat, point.lon);

        if (map._emHoverMarker) {
            map._emHoverMarker.setLatLng(latlng);
        } else {
            var icon = L.divIcon({
                className: 'em-hover-marker',
                html: '<div style="background:#2563eb;width:12px;height:12px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 0 2px #2563eb,0 2px 8px rgba(0,0,0,0.4);"></div>',
                iconSize: [12, 12],
                iconAnchor: [6, 6],
            });
            map._emHoverMarker = L.marker(latlng, { icon: icon }).addTo(map);
        }

        if (map._emHoverLabel) {
            map._emHoverLabel.setLatLng(latlng);
            map._emHoverLabel.setContent(
                '<div style="font-size:11px;padding:2px 6px;background:#fff;border-radius:3px;box-shadow:0 1px 4px rgba(0,0,0,0.2);white-space:nowrap;">' +
                point.d + ' km &middot; ' + point.e + ' m</div>'
            );
        } else {
            map._emHoverLabel = L.tooltip({
                permanent: true,
                direction: 'top',
                offset: L.point(0, -10),
                className: 'em-hover-tooltip',
            }).setLatLng(latlng).setContent(
                '<div style="font-size:11px;white-space:nowrap;">' +
                point.d + ' km &middot; ' + point.e + ' m</div>'
            ).addTo(map);
        }
    }

    function clearMapHover(map) {
        if (map._emHoverMarker) {
            map.removeLayer(map._emHoverMarker);
            map._emHoverMarker = null;
        }
        if (map._emHoverLabel) {
            map.removeLayer(map._emHoverLabel);
            map._emHoverLabel = null;
        }
    }

    function addPoisToMap(map, pois, cluster) {
        var markers = [];
        pois.forEach(function(poi) {
            if (!poi.lat || !poi.lon) return;

            var color = poi.icon_color || '#e74c3c';
            var iconHtml = '<i class="fa-solid ' + (poi.icon_fa || 'fa-location-dot') + '"></i>';

            var markerDiv = L.divIcon({
                className: 'em-custom-marker',
                html: '<div style="background:' + color + ';width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3);color:#fff;font-size:13px">' + iconHtml + '</div>',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -16],
            });

            var popupContent = '<div class="em-poi-popup">';
            popupContent += '<span class="em-poi-icon"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + color + ';margin-right:4px"></span> ' + poi.icon_label + '</span>';
            popupContent += '<h4>' + poi.title + '</h4>';
            if (poi.content) {
                popupContent += '<div class="em-poi-content">' + poi.content + '</div>';
            }
            popupContent += '</div>';

            var marker = L.marker([poi.lat, poi.lon], { icon: markerDiv })
                .bindPopup(popupContent, { maxWidth: 350 });

            markers.push(marker);
        });

        if (cluster && markers.length > 5) {
            var clusterGroup = L.markerClusterGroup({
                chunkedLoading: true,
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
            });
            markers.forEach(function(m) { clusterGroup.addLayer(m); });
            map.addLayer(clusterGroup);
        } else {
            markers.forEach(function(m) { m.addTo(map); });
        }
    }

    window.initEMMasterMap = function(mapId, data) {
        var container = document.getElementById(mapId);
        if (!container) return;

        var wrapper = container.closest('.em-map-wrapper');
        var loading = wrapper ? wrapper.querySelector('.em-map-loading') : null;

        var map = L.map(mapId, {
            center: [46.3, 13.0],
            zoom: 9,
            scrollWheelZoom: true,
        });

        var baseLayers = {}, activeLayer = null;
        var bmDefs = data.basemaps;
        if (!bmDefs || Object.keys(bmDefs).length === 0) {
            bmDefs = {
                OpenStreetMap: { url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', attribution: '&copy; OpenStreetMap', maxZoom: 19, label: 'OpenStreetMap' },
            };
            data.basemap = 'OpenStreetMap';
        }
        var bmKey = data.basemap || 'OpenStreetMap';
        for (var k in bmDefs) {
            var def = bmDefs[k];
            var tl = L.tileLayer(def.url, { attribution: def.attribution, maxZoom: def.maxZoom || 19 });
            baseLayers[def.label || k] = tl;
            if (k === bmKey) activeLayer = tl;
        }
        if (!activeLayer) {
            for (var k2 in baseLayers) { activeLayer = baseLayers[k2]; break; }
        }
        if (activeLayer) activeLayer.addTo(map);
        L.control.layers(baseLayers, null, { collapsed: false }).addTo(map);

        var mmOSM = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });
        L.control.minimap(mmOSM, { position: 'bottomright' }).addTo(map);

        var allBounds = [];
        addResetControl(map, function() {
            if (allBounds.length === 0) return null;
            return allBounds.reduce(function(a, b) { return a.extend(b); });
        });

        if (window.console) console.log('[EM] master basemaps:', Object.keys(bmDefs).length, 'active:', (activeLayer ? 'yes' : 'no'));

        if (data.hikes && data.hikes.length > 0) {
            var gpxLayers = [];
            var hikeMarkers = [];

            data.hikes.forEach(function(hike) {
                if (hike.gpxUrl) {
                    var gpx = new L.GPX(hike.gpxUrl, {
                        async: true,
                        marker_options: {
                            startIconUrl: false,
                            endIconUrl: false,
                            wptIconUrls: {},
                        },
                        polyline_options: {
                            color: '#2563eb',
                            weight: 2,
                            opacity: 0.6,
                        },
                    }).on('loaded', function(e) {
                        var bounds = e.target.getBounds();
                        if (bounds.isValid()) {
                            allBounds.push(bounds);
                        }
                        if (gpxLayers.length === data.hikes.filter(function(h) { return h.gpxUrl; }).length) {
                            if (allBounds.length > 0) {
                                var combined = allBounds.reduce(function(a, b) { return a.extend(b); });
                                map.fitBounds(combined, { padding: [20, 20] });
                            }
                            if (loading) loading.classList.add('em-loaded');
                        }
                    });

                    gpx.addTo(map);
                    gpxLayers.push(gpx);
                }

                var divIcon = L.divIcon({
                    className: 'em-hike-marker',
                    html: '<div style="background:#2563eb;width:16px;height:16px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3);"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8],
                });

                var popup = '<div class="em-poi-popup">';
                popup += '<h4>' + hike.title + '</h4>';
                if (hike.excerpt) popup += '<p style="font-size:0.85em;color:#555;">' + hike.excerpt + '</p>';
                if (hike.distance) popup += '<p><strong>Distanza:</strong> ' + parseFloat(hike.distance).toFixed(1) + ' km</p>';
                if (hike.elevation) popup += '<p><strong>Dislivello+:</strong> ' + hike.elevation + ' m</p>';
                if (hike.link) popup += '<a href="' + hike.link + '" class="em-poi-link">Vedi dettagli &rarr;</a>';
                popup += '</div>';

                if (!hike.gpxUrl) {
                    hikeMarkers.push(L.marker([46.3, 13.0], { icon: divIcon }).bindPopup(popup));
                }
            });

            if (gpxLayers.length === 0 && hikeMarkers.length > 0) {
                var group = L.markerClusterGroup();
                hikeMarkers.forEach(function(m) { group.addLayer(m); });
                map.addLayer(group);
                if (loading) loading.classList.add('em-loaded');
            }

            if (gpxLayers.length === 0 && hikeMarkers.length === 0) {
                if (loading) loading.classList.add('em-loaded');
            }
        } else {
            if (loading) loading.classList.add('em-loaded');
        }
    };

    $(document).ready(function() {
        $('.em-map-container').each(function() {
            var id = $(this).attr('id');
            if (!id) return;

            if (id === 'em-master-map' && typeof window.emMasterMapData !== 'undefined') {
                initEMMasterMap(id, window.emMasterMapData);
                return;
            }

            var num = id.replace('em-map-', '');
            if (typeof window['emMapData_' + num] !== 'undefined') {
                initEMMap(id, window['emMapData_' + num]);
            }
        });
    });

})(jQuery);
