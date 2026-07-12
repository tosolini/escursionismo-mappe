(function (wp, $) {
    var el = wp.element.createElement;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var Spinner = wp.components.Spinner;
    var PanelBody = wp.components.PanelBody;
    var __ = wp.i18n.__;
    var apiFetch = wp.apiFetch;
    var registerBlockType = wp.blocks.registerBlockType;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;

    registerBlockType('escursionismo-mappe/hike-map', {
        apiVersion: 3,
        title: 'Mappa Escursione',
        icon: 'location-alt',
        category: 'embed',
        supports: {
            html: false,
        },
        attributes: {
            hikeId: { type: 'integer', default: 0 },
        },
        edit: function (props) {
            var blockProps = useBlockProps();
            var hikeId = props.attributes.hikeId;
            var setAttributes = props.setAttributes;

            var [hikes, setHikes] = useState(null);
            var [mapData, setMapData] = useState(null);
            var [loading, setLoading] = useState(false);
            var [searchTerm, setSearchTerm] = useState('');

            useEffect(function () {
                apiFetch({ path: '/escursionismo-mappe/v1/hikes' })
                    .then(function (data) { setHikes(data); })
                    .catch(function () { setHikes([]); });
            }, []);

            useEffect(function () {
                if (!hikeId || hikeId === 0) return;
                setLoading(true);
                apiFetch({ path: '/escursionismo-mappe/v1/map-data/' + hikeId })
                    .then(function (data) {
                        setMapData(data);
                        setLoading(false);
                    })
                    .catch(function () {
                        setLoading(false);
                    });
            }, [hikeId]);

            var options = [{ label: 'Seleziona escursione...', value: 0 }];
            if (hikes) {
                hikes.forEach(function (h) {
                    options.push({ label: h.title, value: h.id });
                });
            }

            var filteredOptions = options;
            if (searchTerm && hikes) {
                filteredOptions = options.filter(function (o) {
                    return o.value === 0 || o.label.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1;
                });
            }

            var body;

            if (!hikeId || hikeId === 0) {
                body = el('div', { style: { padding: '20px', textAlign: 'center', color: '#888' } },
                    __('Seleziona una escursione.', 'escursionismo-mappe')
                );
            } else if (loading) {
                body = el('div', { style: { padding: '20px', textAlign: 'center' } }, el(Spinner));
            } else if (mapData) {
                body = el('div', { style: { padding: '16px', background: '#f0f6fc', borderRadius: '4px', textAlign: 'center' } },
                    el('strong', { style: { fontSize: '15px' } }, mapData.title),
                    el('div', { style: { fontSize: '13px', color: '#666', marginTop: '6px' } },
                        (mapData.distance ? mapData.distance + ' km' : '') +
                        (mapData.elevation ? ' \u00b7 ' + mapData.elevation + ' m+' : '') +
                        (mapData.pois ? ' \u00b7 ' + mapData.pois.length + ' POI' : '')
                    ),
                    el('div', { style: { marginTop: '10px', fontSize: '12px', color: '#999', fontStyle: 'italic' } },
                        __('Mappa visibile nella pagina pubblicata.', 'escursionismo-mappe')
                    )
                );
            } else {
                body = el('div', { style: { padding: '20px', textAlign: 'center', color: '#999' } },
                    __('Caricamento...', 'escursionismo-mappe')
                );
            }

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, {
                        title: __('Impostazioni Mappa', 'escursionismo-mappe'),
                        initialOpen: true,
                    }, [
                        el(TextControl, {
                            label: __('Cerca escursione...', 'escursionismo-mappe'),
                            value: searchTerm,
                            onChange: function (v) { setSearchTerm(v); },
                            placeholder: __('Digita per filtrare...', 'escursionismo-mappe'),
                        }),
                        el(SelectControl, {
                            label: __('Escursione', 'escursionismo-mappe'),
                            value: hikeId,
                            options: filteredOptions,
                            onChange: function (id) {
                                setAttributes({ hikeId: parseInt(id, 10) });
                            },
                        }),
                    ])
                ),
                el('div', Object.assign({ key: 'content' }, blockProps),
                    el('div', { style: { padding: '4px' } }, body)
                ),
            ];
        },
        save: function () {
            return null;
        },
    });
})(window.wp, window.jQuery);
