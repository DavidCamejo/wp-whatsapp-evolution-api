jQuery(document).ready(function($) {

    // --- DOM Elements ---
    var $whatsappStatus = $('#wwea-whatsapp-status');
    var $qrCodeDisplay = $('#wwea-qr-code-display');
    var $qrLoading = $('#wwea-qr-loading');
    var $qrError = $('#wwea-qr-error');
    var $generateQrBtn = $('#wwea-generate-qr');
    var $refreshStatusBtn = $('#wwea-refresh-status');
    var $saveNumberBtn = $('#wwea_save_whatsapp_number');
    var $numberSaveStatus = $('#wwea_number_save_status');
    var $whatsappNumberInput = $('#wwea_vendor_whatsapp_number');
    var $displayWhatsappNumber = $('#wwea-display-whatsapp-number');
    var $additionalInfo = $('#wwea-additional-info');
    var $qrArea = $('#wwea-whatsapp-qr-area'); // Main QR/status display area

    // --- Helper Functions ---

    /**
     * Capitalizes the first letter of a string.
     * @param {string} string The input string.
     * @returns {string} The string with the first letter capitalized.
     */
    function capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    /**
     * Escapes HTML to prevent XSS.
     * @param {string} str The string to escape.
     * @returns {string} The escaped string.
     */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Updates the status display on the UI.
     * @param {string} status The new status (e.g., 'connected', 'disconnected').
     */
    function updateStatusUI(status) {
        $whatsappStatus.text(capitalizeFirstLetter(status))
                       .removeClass('wwea-status-connected wwea-status-disconnected wwea-status-scanning wwea-status-error')
                       .addClass('wwea-status-' + status);
    }

    /**
     * Renders additional connection info from n8n/Evolution API.
     * @param {object} info An object containing connection details.
     */
    function renderAdditionalInfo(info) {
        $additionalInfo.empty();
        if (info && Object.keys(info).length > 0) {
            $.each(info, function(key, value) {
                var displayKey = capitalizeFirstLetter(key.replace(/_/g, ' '));
                var displayValue = (typeof value === 'object' && value !== null) ? JSON.stringify(value) : escHtml(value);
                $additionalInfo.append('<p><strong>' + displayKey + ':</strong> ' + displayValue + '</p>');
            });
        } else {
            $additionalInfo.html('<p><small>' + wwea_vars.text.no_additional_info + '</small></p>');
        }
    }

    /**
     * Renders the entire WhatsApp integration UI based on received data.
     * @param {object} data The data received from the AJAX call (status, qr_code, connection_info).
     */
    function renderWhatsAppUI(data) {
        updateStatusUI(data.status);
        $displayWhatsappNumber.text(escHtml($whatsappNumberInput.val() || wwea_vars.text.not_saved_yet)); // Update from input field
        renderAdditionalInfo(data.connection_info);

        if (data.status === 'connected') {
            $qrArea.html(`
                <p class="wwea-connected-message dokan-success">${wwea_vars.text.connected_message}</p>
                <button id="wwea-refresh-status" class="dokan-btn dokan-btn-sm dokan-btn-default">${wwea_vars.text.refresh_status_text}</button>
                `);
            // Re-bind refresh button
            $('#wwea-refresh-status').off('click').on('click', fetchWhatsAppData);
        } else {
            // Render QR generation interface
            $qrArea.html(`
                <p class="wwea-qr-instruction">${wwea_vars.text.scan_qr_instruction}</p>
                <button id="wwea-generate-qr" class="dokan-btn dokan-btn-sm dokan-btn-theme">${wwea_vars.text.connect_generate_qr_text}</button>
                <p id="wwea-qr-loading" class="dokan-info" style="display: none;">${wwea_vars.text.generating_qr}</p>
                <div id="wwea-qr-code-display" class="wwea-qr-code-container"></div>
                <p id="wwea-qr-error" class="dokan-error" style="display: none;"></p>
            `);
            // Re-select elements after re-rendering
            $generateQrBtn = $('#wwea-generate-qr');
            $qrLoading = $('#wwea-qr-loading');
            $qrCodeDisplay = $('#wwea-qr-code-display');
            $qrError = $('#wwea-qr-error');

            if (data.qr_code) {
                $qrCodeDisplay.html('<img src="' + escHtml(data.qr_code) + '" alt="WhatsApp QR Code">');
            } else {
                $qrCodeDisplay.html('<p class="wwea-no-qr">' + wwea_vars.text.no_qr_available + '</p>');
            }

            // Re-bind generate QR button
            $generateQrBtn.off('click').on('click', fetchWhatsAppData); // Both QR and Status are fetched by same AJAX
        }

        if (data.message) {
            if (data.status === 'error') {
                $qrError.text(data.message).show();
            } else {
                $qrError.hide(); // Clear any previous error messages
            }
        }
    }

    // --- AJAX Calls ---

    /**
     * Fetches WhatsApp QR code, status, and other data from the backend.
     */
    function fetchWhatsAppData() {
        $qrLoading.show();
        $qrError.hide();
        $generateQrBtn.prop('disabled', true).text(wwea_vars.text.connecting_whatsapp);
        if ($refreshStatusBtn.length) {
            $refreshStatusBtn.prop('disabled', true);
        }
        updateStatusUI('scanning'); // Optimistic update

        $.ajax({
            url: wwea_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'wwea_dokan_get_whatsapp_data',
                nonce: wwea_vars.nonce
            },
            success: function(response) {
                $qrLoading.hide();
                $generateQrBtn.prop('disabled', false).text(wwea_vars.text.connect_generate_qr_text);
                if ($refreshStatusBtn.length) {
                    $refreshStatusBtn.prop('disabled', false);
                }

                if (response.success) {
                    renderWhatsAppUI(response.data);
                } else {
                    renderWhatsAppUI(response.data); // Render even on error to show partial info/status
                    $qrError.text(response.data.message || wwea_vars.text.api_error + wwea_vars.text.unknown_status).show();
                    updateStatusUI('error');
                }
            },
            error: function(xhr, status, error) {
                $qrLoading.hide();
                $generateQrBtn.prop('disabled', false).text(wwea_vars.text.connect_generate_qr_text);
                if ($refreshStatusBtn.length) {
                    $refreshStatusBtn.prop('disabled', false);
                }
                $qrError.text(wwea_vars.text.api_error + error).show();
                updateStatusUI('error');
            }
        });
    }

    /**
     * Saves the vendor's WhatsApp number.
     */
    $saveNumberBtn.on('click', function() {
        var number = $whatsappNumberInput.val();
        $numberSaveStatus.empty().append('<span class="dokan-loading"></span>'); // Show loading spinner

        $.ajax({
            url: wwea_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'wwea_save_vendor_whatsapp_number',
                nonce: wwea_vars.nonce,
                whatsapp_number: number
            },
            success: function(response) {
                $numberSaveStatus.empty();
                if (response.success) {
                    $numberSaveStatus.html('<span class="dokan-success">' + wwea_vars.text.save_success + '</span>');
                    $displayWhatsappNumber.text(escHtml(number));
                } else {
                    $numberSaveStatus.html('<span class="dokan-error">' + (response.data.message || wwea_vars.text.save_fail) + '</span>');
                }
            },
            error: function() {
                $numberSaveStatus.empty().html('<span class="dokan-error">' + wwea_vars.text.api_error + ' ' + wwea_vars.text.save_fail + '</span>');
            }
        });
    });

    // --- Event Listeners ---

    // Initial fetch when the page loads
    fetchWhatsAppData();

    // Event listener for generate QR/Connect button (re-binds after UI re-render)
    $(document).on('click', '#wwea-generate-qr', fetchWhatsAppData);

    // Event listener for refresh status button (re-binds after UI re-render)
    $(document).on('click', '#wwea-refresh-status', fetchWhatsAppData);

    // Extend wwea_vars with additional text for JS use
    if (typeof wwea_vars !== 'undefined') {
        $.extend(wwea_vars.text, {
            'connected_message': 'Your WhatsApp is connected!',
            'refresh_status_text': 'Refresh Status',
            'disconnect_text': 'Disconnect', // Future
            'scan_qr_instruction': 'Scan the QR code below with your WhatsApp mobile app to connect your account. Make sure your WhatsApp app is logged out before scanning.',
            'connect_generate_qr_text': 'Connect WhatsApp / Generate QR Code',
            'generating_qr': 'Generating QR code, please wait...',
            'no_qr_available': 'No QR code available. Click "Connect WhatsApp / Generate QR Code" to get one.',
            'unknown_error': 'An unknown error occurred.',
            'network_error': 'Network error:',
            'not_saved_yet': 'Not saved yet',
            'no_additional_info': 'No additional connection details available yet.'
        });
    }

});
