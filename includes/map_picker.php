<?php
function renderMapPicker($lat_name = 'latitude', $lng_name = 'longitude', $existing_lat = null, $existing_lng = null) {
    static $leaflet_loaded = false;
    if (!$leaflet_loaded) {
        echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">' . "\n";
        echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>' . "\n";
        $leaflet_loaded = true;
    }

    $uid      = 'mp_' . substr(md5($lat_name . $lng_name . uniqid()), 0, 6);
    $init_lat = $existing_lat ? (float)$existing_lat : 28.2096;
    $init_lng = $existing_lng ? (float)$existing_lng : 83.9856;
    $has      = $existing_lat && $existing_lng;
    ?>
    <input type="hidden" name="<?= htmlspecialchars($lat_name) ?>" id="<?= $uid ?>_lat" value="<?= htmlspecialchars($existing_lat ?? '') ?>">
    <input type="hidden" name="<?= htmlspecialchars($lng_name) ?>" id="<?= $uid ?>_lng" value="<?= htmlspecialchars($existing_lng ?? '') ?>">

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-success btn-sm" id="<?= $uid ?>_btn">
            <i class="bi bi-geo-alt-fill me-1"></i>Pick Location on Map
        </button>
        <span id="<?= $uid ?>_label" class="text-muted small">
            <?= $has ? '📍 ' . $existing_lat . ', ' . $existing_lng : 'No location picked yet' ?>
        </span>
    </div>

    <script>
    // Wait for everything to be ready
    window.addEventListener('load', function() {
        var uid     = '<?= $uid ?>';
        var initLat = <?= $init_lat ?>;
        var initLng = <?= $init_lng ?>;
        var mapInst = null;
        var marker  = null;
        var pendLat = <?= $has ? (float)$existing_lat : 'null' ?>;
        var pendLng = <?= $has ? (float)$existing_lng : 'null' ?>;

        // Inject modal at body level
        var div = document.createElement('div');
        div.innerHTML = '<div class="modal fade" id="' + uid + '_modal" tabindex="-1">'
            + '<div class="modal-dialog modal-lg modal-dialog-centered">'
            + '<div class="modal-content">'
            + '<div class="modal-header">'
            + '<h5 class="modal-title"><i class="bi bi-geo-alt-fill text-success me-2"></i>Pick Location</h5>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>'
            + '</div>'
            + '<div class="modal-body p-0">'
            + '<div class="px-3 pt-2 pb-2 bg-light border-bottom small text-muted"><i class="bi bi-info-circle me-1"></i>Click anywhere on the map to drop a pin, then click Confirm.</div>'
            + '<div id="' + uid + '_map" style="height:420px;"></div>'
            + '</div>'
            + '<div class="modal-footer justify-content-between">'
            + '<span id="' + uid + '_coords" class="text-muted small"><?= $has ? '📍 ' . $existing_lat . ', ' . $existing_lng : 'No location selected' ?></span>'
            + '<div class="d-flex gap-2">'
            + '<button type="button" class="btn btn-outline-secondary btn-sm" id="' + uid + '_gps"><i class="bi bi-crosshair me-1"></i>Use My Location</button>'
            + '<button type="button" class="btn btn-success btn-sm" id="' + uid + '_confirm" data-bs-dismiss="modal"<?= $has ? '' : ' disabled' ?>>Confirm Location</button>'
            + '</div></div></div></div></div>';
        document.body.appendChild(div.firstChild);

        var modalEl = document.getElementById(uid + '_modal');
        var bsModal = new bootstrap.Modal(modalEl);

        document.getElementById(uid + '_btn').addEventListener('click', function() {
            bsModal.show();
        });

        modalEl.addEventListener('shown.bs.modal', function() {
            if (!mapInst) {
                mapInst = L.map(uid + '_map').setView([initLat, initLng], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(mapInst);

                if (pendLat !== null && pendLng !== null) {
                    marker = L.marker([pendLat, pendLng]).addTo(mapInst);
                }

                mapInst.on('click', function(e) {
                    pendLat = e.latlng.lat;
                    pendLng = e.latlng.lng;
                    if (marker) { marker.setLatLng(e.latlng); }
                    else        { marker = L.marker(e.latlng).addTo(mapInst); }
                    document.getElementById(uid + '_coords').textContent = '📍 ' + pendLat.toFixed(5) + ', ' + pendLng.toFixed(5);
                    document.getElementById(uid + '_confirm').disabled = false;
                });
            } else {
                mapInst.invalidateSize();
            }
        });

        document.getElementById(uid + '_gps').addEventListener('click', function() {
            if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
            navigator.geolocation.getCurrentPosition(function(pos) {
                pendLat = pos.coords.latitude;
                pendLng = pos.coords.longitude;
                mapInst.setView([pendLat, pendLng], 15);
                if (marker) { marker.setLatLng([pendLat, pendLng]); }
                else        { marker = L.marker([pendLat, pendLng]).addTo(mapInst); }
                document.getElementById(uid + '_coords').textContent = '📍 ' + pendLat.toFixed(5) + ', ' + pendLng.toFixed(5);
                document.getElementById(uid + '_confirm').disabled = false;
            }, function() { alert('Could not get your location.'); });
        });

        document.getElementById(uid + '_confirm').addEventListener('click', function() {
            if (pendLat !== null && pendLng !== null) {
                document.getElementById(uid + '_lat').value = pendLat.toFixed(6);
                document.getElementById(uid + '_lng').value = pendLng.toFixed(6);
                document.getElementById(uid + '_label').textContent = '📍 ' + pendLat.toFixed(5) + ', ' + pendLng.toFixed(5);
            }
        });
    });
    </script>
    <?php
}
?>
