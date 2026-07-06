(function () {
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const navToggle = $('.nav-toggle');
    if (navToggle) {
        navToggle.addEventListener('click', () => $('.nav-links')?.classList.toggle('open'));
    }

    const packageCosts = {
        'Basic Pit Check': 3500,
        'Standard Service': 12500,
        'Full PitCrew Service': 22000,
        'Emergency Pit Stop': 15000,
        'Fleet Care Plan': 30000
    };

    function formatRs(value) {
        return 'Rs. ' + Number(value || 0).toLocaleString() + '+';
    }

    function distanceKm(aLat, aLng, bLat, bLng) {
        const R = 6371;
        const dLat = (bLat - aLat) * Math.PI / 180;
        const dLng = (bLng - aLng) * Math.PI / 180;
        const x = Math.sin(dLat / 2) ** 2 + Math.cos(aLat * Math.PI / 180) * Math.cos(bLat * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
    }

    function getNumber(value) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : null;
    }

    function updateBookingEstimate() {
        const pack = $('#package_type');
        const urgency = $('#urgency_level');
        const serviceMode = $('#service_mode');
        const pickup = $('#pickup_required');
        const mileage = $('#mileage');
        if (!pack || !urgency) return;

        let cost = packageCosts[pack.value] || 12500;
        if (urgency.value === 'Urgent') cost += 2500;
        if (urgency.value === 'Emergency') cost += 5000;
        if (serviceMode && serviceMode.value === 'mobile_service') cost += 3500;
        if (pickup && pickup.value === 'Yes') cost += 2000;

        const costEl = $('#estimateCost');
        if (costEl) costEl.textContent = formatRs(cost);

        const eta = $('#etaPreview');
        if (eta) eta.textContent = urgency.value === 'Emergency' ? 'Priority review' : (urgency.value === 'Urgent' ? 'Fast review' : 'Same-day review');

        const suggestion = $('#packageSuggestion');
        const tip = $('#smartTip');
        if (mileage && mileage.value) {
            const km = parseInt(mileage.value, 10);
            let msg = 'Standard Service is suitable for regular vehicle care.';
            let suggested = 'Standard Service';
            if (km > 100000) {
                suggested = 'Full PitCrew Service';
                msg = 'High mileage vehicles benefit from a complete maintenance inspection.';
            } else if (km > 60000) {
                suggested = 'Standard Service';
                msg = 'A standard service is recommended for mid-to-high mileage use.';
            } else if (km < 30000) {
                suggested = 'Basic Pit Check';
                msg = 'A basic pit check may be enough for newer vehicles unless issues are noticed.';
            }
            if (suggestion) suggestion.textContent = suggested;
            if (tip) tip.textContent = msg;
        }
    }

    ['#package_type', '#urgency_level', '#service_mode', '#pickup_required', '#mileage'].forEach(sel => {
        const el = $(sel);
        if (!el) return;
        el.addEventListener('change', updateBookingEstimate);
        el.addEventListener('input', updateBookingEstimate);
    });
    updateBookingEstimate();

    let mapPromise = null;

    function googleMapsKey() {
        return window.PITCREW_CONFIG && window.PITCREW_CONFIG.mapsKey ? window.PITCREW_CONFIG.mapsKey : '';
    }

    function loadGoogleMaps() {
        if (!googleMapsKey()) return Promise.reject(new Error('No Google Maps key configured'));
        if (window.google && window.google.maps) return Promise.resolve(window.google.maps);
        if (mapPromise) return mapPromise;

        mapPromise = new Promise((resolve, reject) => {
            window.__pitcrewMapReady = () => resolve(window.google.maps);

            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(googleMapsKey()) + '&libraries=places&callback=__pitcrewMapReady';
            script.async = true;
            script.defer = true;
            script.onerror = () => reject(new Error('Unable to load Google Maps'));
            document.head.appendChild(script);
        });

        return mapPromise;
    }

    function setMapFallback(el, message) {
        if (!el) return;
        el.classList.add('map-placeholder');
        el.innerHTML = '<div><strong>Map feature ready</strong><br>' + message + '</div>';
    }

    function makeMarker(map, position, title) {
        return new google.maps.Marker({ map, position, title });
    }

    function findNearestCentre(lat, lng) {
        const centres = window.PITCREW_CENTRES || [];
        let nearest = null;

        centres.forEach(c => {
            const cLat = getNumber(c.latitude);
            const cLng = getNumber(c.longitude);
            if (cLat === null || cLng === null) return;

            const d = distanceKm(lat, lng, cLat, cLng);
            if (!nearest || d < nearest.distance) nearest = { centre: c, distance: d };
        });

        return nearest;
    }

    function applyCustomerLocation(lat, lng, sourceLabel) {
        const latInput = $('#customer_lat');
        const lngInput = $('#customer_lng');
        const nearestBox = $('#nearestCentre');
        const locationMessage = $('#locationMessage');

        if (latInput) latInput.value = Number(lat).toFixed(7);
        if (lngInput) lngInput.value = Number(lng).toFixed(7);

        const nearest = findNearestCentre(lat, lng);

        if (nearest) {
            if (nearestBox) {
                nearestBox.innerHTML = '<strong>' + nearest.centre.centre_name + '</strong><br>' + nearest.centre.city + ' • approx. ' + nearest.distance.toFixed(1) + ' km away';
            }

            const select = $('#service_centre_id');
            if (select) select.value = nearest.centre.id;

            const eta = $('#etaPreview');
            if (eta) eta.textContent = 'Approx. ' + Math.max(15, Math.round((nearest.distance / 30) * 60)) + ' min travel time';
        }

        if (locationMessage && sourceLabel) {
            locationMessage.textContent = sourceLabel;
        }

        if (window.PITCREW_BOOKING_MAP && window.google && window.google.maps) {
            const userPos = { lat: Number(lat), lng: Number(lng) };

            if (window.PITCREW_BOOKING_MAP.userMarker) {
                window.PITCREW_BOOKING_MAP.userMarker.setMap(null);
            }

            window.PITCREW_BOOKING_MAP.userMarker = makeMarker(window.PITCREW_BOOKING_MAP.map, userPos, 'Selected customer location');
            window.PITCREW_BOOKING_MAP.map.panTo(userPos);
            window.PITCREW_BOOKING_MAP.map.setZoom(13);
        }
    }

    function initializeBookingMap() {
        const el = $('#bookingMap');
        const centres = window.PITCREW_CENTRES || [];

        if (!el || !centres.length) return;

        if (!googleMapsKey()) {
            setMapFallback(el, 'Add a Google Maps API key in config.php to show the interactive centre map. The centre selector still works without it.');
            return;
        }

        loadGoogleMaps().then(() => {
            el.classList.remove('map-placeholder');

            const first = centres[0];
            const map = new google.maps.Map(el, {
                center: { lat: parseFloat(first.latitude), lng: parseFloat(first.longitude) },
                zoom: 11,
                mapTypeControl: false,
                streetViewControl: false
            });

            const bounds = new google.maps.LatLngBounds();

            centres.forEach(c => {
                const pos = { lat: parseFloat(c.latitude), lng: parseFloat(c.longitude) };
                bounds.extend(pos);
                makeMarker(map, pos, c.centre_name + ' - ' + c.city);
            });

            map.fitBounds(bounds);
            window.PITCREW_BOOKING_MAP = { map, bounds };
        }).catch(() => {
            setMapFallback(el, 'The map could not be loaded. Check the Google Maps API key and HTTP referrer restriction.');
        });
    }

    initializeBookingMap();

    function initializeAddressAutocomplete() {
        const addressInput = $('#customer_address');
        if (!addressInput || !googleMapsKey()) return;

        loadGoogleMaps().then(() => {
            if (!window.google || !google.maps || !google.maps.places) {
                const msg = $('#locationMessage');
                if (msg) msg.textContent = 'Address suggestions are not available. Please enable Places API for this key.';
                return;
            }

            const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                componentRestrictions: { country: 'lk' },
                fields: ['formatted_address', 'geometry', 'name']
            });

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();
                if (!place || !place.geometry || !place.geometry.location) return;

                addressInput.value = place.formatted_address || place.name || addressInput.value;

                applyCustomerLocation(
                    place.geometry.location.lat(),
                    place.geometry.location.lng(),
                    'Address selected. Nearest service centre has been estimated.'
                );
            });
        }).catch(() => {
            const msg = $('#locationMessage');
            if (msg) msg.textContent = 'Address suggestions could not be loaded. You can still type the address manually.';
        });
    }

    initializeAddressAutocomplete();

    const useLocationBtn = $('#useLocationBtn');

    if (useLocationBtn) {
        useLocationBtn.addEventListener('click', () => {
            const nearestBox = $('#nearestCentre');
            const locationMessage = $('#locationMessage');

            if (!navigator.geolocation) {
                if (nearestBox) nearestBox.textContent = 'Location is not supported in this browser. Please type your address or select a centre manually.';
                return;
            }

            const isSecureForLocation = window.location.protocol === 'https:' ||
                window.location.hostname === 'localhost' ||
                window.location.hostname === '127.0.0.1';

            if (!isSecureForLocation) {
                if (nearestBox) nearestBox.textContent = 'Location permission is blocked on normal HTTP websites.';
                if (locationMessage) locationMessage.textContent = 'Please type your address using the search box, or select a service centre manually. The same button can request permission after the site is served through HTTPS.';
                return;
            }

            if (nearestBox) nearestBox.textContent = 'Requesting location permission...';
            if (locationMessage) locationMessage.textContent = '';

            navigator.geolocation.getCurrentPosition(pos => {
                applyCustomerLocation(
                    pos.coords.latitude,
                    pos.coords.longitude,
                    'Current location selected. Nearest service centre has been estimated.'
                );
            }, () => {
                if (nearestBox) nearestBox.textContent = 'Location permission was not allowed. Please type your address or select a centre manually.';
            }, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
        });
    }

    const tipBtn = $('#getTipBtn');

    if (tipBtn) {
        tipBtn.addEventListener('click', () => {
            const km = parseInt($('#tipMileage')?.value || '0', 10);
            const style = $('#tipDriving')?.value || '';

            let result = 'Basic Pit Check is suitable for a quick safety review.';

            if (km > 100000 || style === 'Commercial/fleet use') {
                result = 'Full PitCrew Service is recommended because the vehicle has higher usage demands.';
            } else if (km > 60000 || style === 'Daily city driving') {
                result = 'Standard Service with brake inspection is recommended for regular city use.';
            } else if (style === 'Long-distance driving') {
                result = 'Pre-Trip Safety Check is recommended before long-distance travel.';
            }

            const out = $('#tipResult');
            if (out) out.textContent = result;
        });
    }

    const activeLocationWatches = {};

    function isSecureForGeolocation() {
        return window.location.protocol === 'https:' ||
            window.location.hostname === 'localhost' ||
            window.location.hostname === '127.0.0.1';
    }

    function getLiveLocationPanel(btn) {
        return btn.closest('.live-location-panel');
    }

    function getLiveLocationParts(btn) {
        const panel = getLiveLocationPanel(btn);

        return {
            panel,
            statusEl: panel ? $('.live-location-status', panel) : null,
            pulseEl: panel ? $('.live-location-pulse', panel) : null,
            lastEl: panel ? $('.live-location-last', panel) : null,
            startBtn: panel ? $('.start-live-location', panel) : null,
            stopBtn: panel ? $('.stop-live-location', panel) : null,
            shareBtn: panel ? $('.share-location', panel) : null
        };
    }

    function setLiveLocationStatus(btn, message, type) {
        const { statusEl, pulseEl } = getLiveLocationParts(btn);

        if (statusEl) {
            statusEl.textContent = message;
            statusEl.classList.remove('success', 'error', 'warning', 'active');
            if (type) statusEl.classList.add(type);
        }

        if (pulseEl) {
            pulseEl.classList.remove('is-live', 'is-error', 'is-warning');

            if (type === 'success' || type === 'active') {
                pulseEl.classList.add('is-live');
            }

            if (type === 'error') {
                pulseEl.classList.add('is-error');
            }

            if (type === 'warning') {
                pulseEl.classList.add('is-warning');
            }
        }
    }

    function getEtaFromButton(btn) {
        const value = parseInt(btn.dataset.eta || '30', 10);
        return Number.isFinite(value) ? Math.max(0, Math.min(240, value)) : 30;
    }

    function updateStaffJobUi(btn, data, eta) {
        const bookingId = btn.dataset.booking;
        const { lastEl } = getLiveLocationParts(btn);
        const updatedText = data.updated_at || 'Just now';

        const updatedEl = $('.job-location-updated[data-booking="' + bookingId + '"]');
        if (updatedEl) updatedEl.textContent = updatedText;

        if (lastEl) lastEl.textContent = 'Last shared: ' + updatedText;

        const etaEl = $('.job-latest-eta[data-booking="' + bookingId + '"]');
        if (etaEl) etaEl.textContent = (data.eta_minutes || eta || 30) + ' min';

        const card = $('#job-' + bookingId);
        const badge = card ? $('.badge', card) : null;

        if (badge && data.status) {
            badge.textContent = data.status;
            badge.className = 'badge status-' + String(data.status).toLowerCase().replace(/\s+/g, '-');
        }
    }

    function sendStaffLocation(btn, lat, lng, eta) {
        return fetch('staff-location-update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: btn.dataset.csrf || '',
                booking_id: btn.dataset.booking,
                latitude: lat,
                longitude: lng,
                eta_minutes: eta || 30
            })
        }).then(response => {
            return response.json().catch(() => ({
                ok: false,
                message: 'Server returned an invalid response.'
            }));
        });
    }

    function explainLocationError(error) {
        if (error && error.code === 1) {
            return 'Location permission was blocked. Please allow location access for this site and try again.';
        }

        if (error && error.code === 2) {
            return 'Your device could not detect its location. Turn on GPS/location services and try again.';
        }

        if (error && error.code === 3) {
            return 'Location request timed out. Move to an open area or try again.';
        }

        return 'Could not read your current location. Please try again.';
    }

    function showHttpsRequired(btn) {
        const message = 'Real GPS sharing is blocked because this page is opened with HTTP. To share the real staff location automatically, open the website through HTTPS.';

        setLiveLocationStatus(btn, message, 'warning');

        /*
         * This alert is intentional. On HTTP, browser GPS stops before the permission popup.
         * The alert makes sure the staff member understands why nothing is opening.
         */
        alert(message);
    }

    function shareStaffLocationOnce(btn) {
        if (!navigator.geolocation) {
            setLiveLocationStatus(btn, 'Location sharing is not supported in this browser.', 'error');
            return;
        }

        if (!isSecureForGeolocation()) {
            showHttpsRequired(btn);
            return;
        }

        const eta = getEtaFromButton(btn);
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Requesting permission...';
        setLiveLocationStatus(btn, 'Requesting browser location permission...', 'warning');

        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            sendStaffLocation(btn, lat, lng, eta).then(data => {
                if (data.ok) {
                    updateStaffJobUi(btn, data, eta);
                    btn.textContent = 'Location Shared';
                    setLiveLocationStatus(btn, 'Current location shared successfully. The customer tracking page can now update.', 'success');
                } else {
                    btn.textContent = 'Could Not Share';
                    setLiveLocationStatus(btn, data.message || 'Could not share location.', 'error');
                }

                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2500);
            }).catch(() => {
                btn.textContent = 'Could Not Share';
                setLiveLocationStatus(btn, 'Could not contact the server. Try again.', 'error');

                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2500);
            });
        }, error => {
            btn.textContent = originalText;
            btn.disabled = false;
            setLiveLocationStatus(btn, explainLocationError(error), 'error');
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    }

    function startLiveStaffLocation(btn) {
        const bookingId = btn.dataset.booking;
        const { stopBtn, shareBtn } = getLiveLocationParts(btn);

        if (activeLocationWatches[bookingId]) {
            setLiveLocationStatus(btn, 'Live location is already running for this job. Keep this tab open.', 'active');
            return;
        }

        if (!navigator.geolocation) {
            setLiveLocationStatus(btn, 'Location sharing is not supported in this browser.', 'error');
            return;
        }

        if (!isSecureForGeolocation()) {
            showHttpsRequired(btn);
            return;
        }

        const eta = getEtaFromButton(btn);

        btn.disabled = true;

        if (shareBtn) {
            shareBtn.disabled = true;
        }

        if (stopBtn) {
            stopBtn.disabled = false;
        }

        btn.textContent = 'Sharing Live...';
        setLiveLocationStatus(btn, 'Live sharing started. Keep this tab open while travelling.', 'active');

        const watchId = navigator.geolocation.watchPosition(pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            sendStaffLocation(btn, lat, lng, eta).then(data => {
                if (data.ok) {
                    updateStaffJobUi(btn, data, eta);
                    setLiveLocationStatus(btn, 'Live location updated at ' + (data.updated_at || 'now') + '. Keep this tab open.', 'active');
                } else {
                    setLiveLocationStatus(btn, data.message || 'Live location update failed.', 'error');
                }
            }).catch(() => {
                setLiveLocationStatus(btn, 'Network error while updating live location.', 'error');
            });
        }, error => {
            stopLiveStaffLocation(btn);
            setLiveLocationStatus(btn, explainLocationError(error), 'error');
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 5000
        });

        activeLocationWatches[bookingId] = watchId;
    }

    function stopLiveStaffLocation(btn) {
        const bookingId = btn.dataset.booking;
        const { startBtn, shareBtn } = getLiveLocationParts(btn);

        if (activeLocationWatches[bookingId]) {
            navigator.geolocation.clearWatch(activeLocationWatches[bookingId]);
            delete activeLocationWatches[bookingId];
        }

        if (startBtn) {
            startBtn.disabled = false;
            startBtn.textContent = 'Start Live Location';
        }

        if (shareBtn) {
            shareBtn.disabled = false;
        }

        btn.disabled = true;
        setLiveLocationStatus(btn, 'Live location sharing stopped.', 'warning');
    }

    /*
     * Delegated click handler.
     * This is more reliable than attaching listeners only once because it still works
     * if the buttons are rendered later or browser cache behaves strangely.
     */
    document.addEventListener('click', function (event) {
        const startBtn = event.target.closest('.start-live-location');
        const shareBtn = event.target.closest('.share-location');
        const stopBtn = event.target.closest('.stop-live-location');

        if (startBtn) {
            event.preventDefault();
            startLiveStaffLocation(startBtn);
            return;
        }

        if (shareBtn) {
            event.preventDefault();
            shareStaffLocationOnce(shareBtn);
            return;
        }

        if (stopBtn) {
            event.preventDefault();
            stopLiveStaffLocation(stopBtn);
        }
    });

    function updateTrackingText(data) {
        if (!data || !data.ok) return;

        const status = $('#liveTrackStatus');
        const eta = $('#liveTrackEta');
        const updated = $('#liveTrackUpdated');
        const coords = $('#liveTrackCoords');

        if (status && data.booking.status) {
            status.textContent = data.booking.status;
        }

        const etaValue = (data.location && data.location.eta_minutes) || data.booking.eta_minutes;

        if (eta) {
            eta.textContent = etaValue ? etaValue + ' min' : 'Waiting for staff update';
        }

        if (updated) {
            if (data.location && data.location.updated_at) {
                updated.textContent = data.location.updated_at + (data.location_is_stale ? ' (last known)' : '');
            } else {
                updated.textContent = 'Not shared yet';
            }
        }

        if (coords) {
            if (data.location && data.location.latitude && data.location.longitude) {
                coords.textContent = Number(data.location.latitude).toFixed(5) + ', ' + Number(data.location.longitude).toFixed(5);
            } else {
                coords.textContent = 'Waiting for location';
            }
        }
    }

    function initTrackMap() {
        const el = $('#trackMap');
        const track = window.PITCREW_TRACK;

        if (!el || !track || !track.booking) return;

        if (!googleMapsKey()) {
            setMapFallback(el, 'Add a Google Maps API key in config.php to display the live map. Status and ETA still refresh below.');
            pollTracking(false);
            return;
        }

        loadGoogleMaps().then(() => {
            el.classList.remove('map-placeholder');

            const booking = track.booking;
            const centreLat = getNumber(booking.centre_lat);
            const centreLng = getNumber(booking.centre_lng);
            const customerLat = getNumber(booking.customer_lat);
            const customerLng = getNumber(booking.customer_lng);
            const staffLat = track.staffLocation ? getNumber(track.staffLocation.latitude) : null;
            const staffLng = track.staffLocation ? getNumber(track.staffLocation.longitude) : null;

            const start = staffLat && staffLng
                ? { lat: staffLat, lng: staffLng }
                : (customerLat && customerLng
                    ? { lat: customerLat, lng: customerLng }
                    : (centreLat && centreLng
                        ? { lat: centreLat, lng: centreLng }
                        : { lat: 6.927079, lng: 79.861244 }));

            const map = new google.maps.Map(el, {
                center: start,
                zoom: 12,
                mapTypeControl: false,
                streetViewControl: false
            });

            const bounds = new google.maps.LatLngBounds();
            const markers = {};

            if (centreLat && centreLng) {
                const pos = { lat: centreLat, lng: centreLng };
                markers.centre = makeMarker(map, pos, 'PitCrew service centre');
                bounds.extend(pos);
            }

            if (customerLat && customerLng) {
                const pos = { lat: customerLat, lng: customerLng };
                markers.customer = makeMarker(map, pos, 'Customer location');
                bounds.extend(pos);
            }

            if (staffLat && staffLng) {
                const pos = { lat: staffLat, lng: staffLng };
                markers.staff = makeMarker(map, pos, 'PitCrew team location');
                bounds.extend(pos);
            }

            if (!bounds.isEmpty()) {
                map.fitBounds(bounds);
            }

            window.PITCREW_TRACK_MAP = { map, markers, bounds };
            pollTracking(true);
        }).catch(() => {
            setMapFallback(el, 'The map could not be loaded. Check the Google Maps API key and restriction settings.');
            pollTracking(false);
        });
    }

    function pollTracking(updateMap) {
        const track = window.PITCREW_TRACK;

        if (!track || !track.bookingInput || !track.email) return;

        const url = 'track-data.php?booking=' + encodeURIComponent(track.bookingInput) + '&email=' + encodeURIComponent(track.email);

        const run = () => {
            fetch(url).then(r => r.json()).then(data => {
                updateTrackingText(data);

                if (updateMap && data.ok && data.location && window.PITCREW_TRACK_MAP && window.google && window.google.maps) {
                    const lat = getNumber(data.location.latitude);
                    const lng = getNumber(data.location.longitude);

                    if (lat && lng) {
                        const pos = { lat, lng };

                        if (!window.PITCREW_TRACK_MAP.markers.staff) {
                            window.PITCREW_TRACK_MAP.markers.staff = makeMarker(window.PITCREW_TRACK_MAP.map, pos, 'PitCrew team location');
                        } else {
                            window.PITCREW_TRACK_MAP.markers.staff.setPosition(pos);
                        }

                        window.PITCREW_TRACK_MAP.map.panTo(pos);
                    }
                }
            }).catch(() => {});
        };

        run();
        window.PITCREW_TRACK_INTERVAL = setInterval(run, 10000);
    }

    initTrackMap();

    function initGoogleSignIn() {
        const clientId = window.PITCREW_CONFIG && window.PITCREW_CONFIG.googleClientId;

        if (!clientId || !window.google || !google.accounts || !google.accounts.id) return;

        google.accounts.id.initialize({
            client_id: clientId,
            callback: function (response) {
                fetch('google-auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ credential: response.credential })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        window.location.href = data.redirect;
                    } else {
                        alert(data.message || 'Google sign-in failed.');
                    }
                })
                .catch(() => alert('Google sign-in failed.'));
            }
        });

        $$('#googleSignIn').forEach(box => {
            google.accounts.id.renderButton(box, {
                theme: 'outline',
                size: 'large',
                width: 320,
                text: 'continue_with'
            });
        });
    }

    if (window.PITCREW_CONFIG && window.PITCREW_CONFIG.googleClientId) {
        const waitGsi = setInterval(() => {
            if (window.google && google.accounts && google.accounts.id) {
                clearInterval(waitGsi);
                initGoogleSignIn();
            }
        }, 250);

        setTimeout(() => clearInterval(waitGsi), 8000);
    }

    function initEmployeeAssignmentPickers() {
        $$('.employee-picker').forEach((picker) => {
            if (picker.dataset.ready === '1') return;
            picker.dataset.ready = '1';

            const select = $('.employee-hidden-select', picker);
            const chipList = $('.employee-chip-list', picker);
            const search = $('.employee-search', picker);
            const suggestions = $('.employee-suggestions', picker);

            if (!select || !chipList || !search || !suggestions) return;

            const options = Array.from(select.options).map((option) => {
                const parts = option.textContent.split(' - ');
                return {
                    id: option.value,
                    label: option.textContent.trim(),
                    name: (parts[0] || option.textContent).trim(),
                    job: (parts.slice(1).join(' - ') || option.dataset.jobTitle || '').trim(),
                    option
                };
            });

            const selected = new Set(options.filter((item) => item.option.selected).map((item) => item.id));

            function syncSelect() {
                options.forEach((item) => {
                    item.option.selected = selected.has(item.id);
                });
            }

            function renderChips() {
                chipList.innerHTML = '';
                chipList.classList.toggle('is-empty', selected.size === 0);

                options.filter((item) => selected.has(item.id)).forEach((item) => {
                    const chip = document.createElement('span');
                    chip.className = 'employee-chip';
                    chip.innerHTML = '<span>' + item.name + (item.job ? ' <small>' + item.job + '</small>' : '') + '</span>';

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.setAttribute('aria-label', 'Remove ' + item.name);
                    remove.textContent = '×';
                    remove.addEventListener('click', () => {
                        selected.delete(item.id);
                        syncSelect();
                        renderChips();
                        search.focus();
                    });

                    chip.appendChild(remove);
                    chipList.appendChild(chip);
                });
            }

            function closeSuggestions() {
                suggestions.classList.remove('open');
                suggestions.innerHTML = '';
            }

            function addEmployee(item) {
                selected.add(item.id);
                syncSelect();
                renderChips();
                search.value = '';
                closeSuggestions();
                search.focus();
            }

            function renderSuggestions() {
                const query = search.value.trim().toLowerCase();
                suggestions.innerHTML = '';

                if (!query) {
                    closeSuggestions();
                    return;
                }

                const available = options.filter((item) => !selected.has(item.id));
                const startsWith = available.filter((item) => item.name.toLowerCase().startsWith(query));
                const includes = available.filter((item) => !item.name.toLowerCase().startsWith(query) && item.name.toLowerCase().includes(query));
                const matches = [...startsWith, ...includes].slice(0, 8);

                if (!matches.length) {
                    const empty = document.createElement('div');
                    empty.className = 'employee-suggestion';
                    empty.textContent = 'No matching employee found';
                    suggestions.appendChild(empty);
                    suggestions.classList.add('open');
                    return;
                }

                matches.forEach((item) => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'employee-suggestion';
                    row.innerHTML = '<span><strong>' + item.name + '</strong>' + (item.job ? '<span>' + item.job + '</span>' : '') + '</span><span>Add</span>';
                    row.addEventListener('click', () => addEmployee(item));
                    suggestions.appendChild(row);
                });

                suggestions.classList.add('open');
            }

            search.addEventListener('input', renderSuggestions);
            search.addEventListener('focus', renderSuggestions);
            search.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    const first = $('.employee-suggestion', suggestions);
                    if (first && suggestions.classList.contains('open')) {
                        event.preventDefault();
                        first.click();
                    }
                }
                if (event.key === 'Escape') closeSuggestions();
            });

            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target)) closeSuggestions();
            });

            syncSelect();
            renderChips();
        });
    }

    initEmployeeAssignmentPickers();


    function initTipsFeatureCards() {
        const showcase = document.querySelector('[data-tips-showcase]');
        if (!showcase) return;

        const cards = Array.from(showcase.querySelectorAll('.tips-feature-card'));
        if (!cards.length) return;

        function activateCard(selectedCard) {
            if (selectedCard.classList.contains('is-active')) return;

            cards.forEach(card => {
                card.classList.remove('is-opening');
            });

            selectedCard.classList.add('is-opening');

            cards.forEach(card => {
                const selected = card === selectedCard;
                card.classList.toggle('is-active', selected);
                card.setAttribute('aria-expanded', selected ? 'true' : 'false');
            });

            window.setTimeout(() => {
                selectedCard.classList.remove('is-opening');
            }, 760);
        }

        cards.forEach((card, index) => {
            card.addEventListener('click', () => activateCard(card));

            card.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activateCard(card);
                }

                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                    event.preventDefault();
                    const next = cards[index + 1] || cards[0];
                    next.focus();
                    activateCard(next);
                }

                if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const previous = cards[index - 1] || cards[cards.length - 1];
                    previous.focus();
                    activateCard(previous);
                }
            });
        });
    }

    initTipsFeatureCards();

})();