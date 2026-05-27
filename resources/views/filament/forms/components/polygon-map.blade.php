@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js" defer></script>
@endonce

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        wire:ignore
        x-data="polygonMap({
            statePath: @js($getStatePath()),
            center: [{{ $getDefaultLatitude() }}, {{ $getDefaultLongitude() }}],
            zoom: {{ $getDefaultZoom() }},
        })"
        x-init="init()"
        class="space-y-2"
    >
        <div x-ref="mapContainer"
             style="height: {{ $getHeight() }}px;"
             class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-white/10"></div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Используйте инструменты на карте слева, чтобы нарисовать или отредактировать полигон зоны.
            Сохранение полигона произойдёт автоматически — затем нажмите «Сохранить» внизу формы.
        </p>
    </div>
</x-dynamic-component>

<script>
    if (typeof window.polygonMap === 'undefined') {
        window.polygonMap = function (config) {
            return {
                map: null,
                drawnItems: null,
                init() {
                    const start = () => {
                        if (typeof L === 'undefined' || typeof L.Control === 'undefined' || typeof L.Control.Draw === 'undefined') {
                            setTimeout(start, 100);
                            return;
                        }
                        this.setupMap(config);
                    };
                    start();
                },
                setupMap(config) {
                    this.map = L.map(this.$refs.mapContainer).setView(config.center, config.zoom);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    this.drawnItems = new L.FeatureGroup();
                    this.map.addLayer(this.drawnItems);

                    const points = this.$wire.get(config.statePath);
                    if (Array.isArray(points) && points.length >= 3) {
                        const polygon = L.polygon(points, { color: '#3b82f6' }).addTo(this.drawnItems);
                        this.map.fitBounds(polygon.getBounds(), { padding: [20, 20] });
                    }

                    const drawControl = new L.Control.Draw({
                        position: 'topleft',
                        edit: {
                            featureGroup: this.drawnItems,
                            remove: true,
                        },
                        draw: {
                            polyline: false,
                            rectangle: false,
                            circle: false,
                            marker: false,
                            circlemarker: false,
                            polygon: {
                                allowIntersection: false,
                                showArea: true,
                                shapeOptions: { color: '#3b82f6' },
                            },
                        },
                    });
                    this.map.addControl(drawControl);

                    this.map.on(L.Draw.Event.CREATED, (event) => {
                        this.drawnItems.clearLayers();
                        this.drawnItems.addLayer(event.layer);
                        this.sync();
                    });
                    this.map.on(L.Draw.Event.EDITED, () => this.sync());
                    this.map.on(L.Draw.Event.DELETED, () => this.sync());
                },
                sync() {
                    const layers = this.drawnItems.getLayers();
                    if (layers.length === 0) {
                        this.$wire.set(config.statePath, []);
                        return;
                    }
                    const latlngs = layers[0].getLatLngs()[0];
                    const points = latlngs.map((p) => [p.lat, p.lng]);
                    this.$wire.set(config.statePath, points);
                },
            };
        };
    }
</script>
