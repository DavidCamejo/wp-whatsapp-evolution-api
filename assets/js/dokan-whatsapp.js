jQuery(document).ready(function($) {
    // Verificar si wpweaDokan está definido antes de usarlo
    if (typeof wpweaDokan === 'undefined') {
        console.error('wpweaDokan object is not defined. Ensure dokan-whatsapp.js is correctly localized.');
        return;
    }

    var api_url = wpweaDokan.rest_url;
    var nonce = wpweaDokan.nonce;
    var i18n = wpweaDokan.i18n;

    var $statusSpan = $('#whatsapp_connection_status');
    var $qrContainer = $('#qr_code_container');
    var $qrImage = $('#qr_image');
    var $qrMessage = $('#qr_message');
    var $qrLoadingMessage = $('#qr_loading_message');
    var $whatsappMessageStatus = $('#whatsapp_message_status');
    var $whatsappErrorMessage = $('#whatsapp_error_message');

    function updateStatusUI(status) {
        $statusSpan.text(status.toUpperCase());
        $statusSpan.removeClass('dokan-label-connected dokan-label-disconnected dokan-label-pending_qr_scan dokan-label-qrcode dokan-label-unknown dokan-label-checking')
                   .addClass('dokan-label-' + status.toLowerCase().replace(/_/g, '-'));
    }

    function showStatusMessage(message, type = 'info') {
        $whatsappMessageStatus.removeClass('dokan-alert-success dokan-alert-info dokan-alert-warning dokan-alert-danger')
                              .addClass('dokan-alert-' + type)
                              .html(message)
                              .show();
    }

    function hideStatusMessage() {
        $whatsappMessageStatus.hide().empty();
    }

    function showErrorMessage(message) {
        $whatsappErrorMessage.text(message).show();
    }

    function hideErrorMessage() {
        $whatsappErrorMessage.hide();
    }

    // Función para validar el formato del número de teléfono en el cliente
    function isValidPhoneNumber(number) {
        // Permite un '+' opcional al inicio, seguido de solo dígitos. Mínimo 7 dígitos.
        return /^\+?\d{7,}$/.test(number);
    }

    // Función para verificar el estado de la sesión
    function checkStatus() {
        hideErrorMessage();
        hideStatusMessage();
        updateStatusUI('checking');
        $qrMessage.hide();
        $qrLoadingMessage.text(i18n.checkingStatus).show(); // Muestra el mensaje de carga

        $.ajax({
            url: api_url + 'estado-sesion',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                if (response.success && response.current_status) {
                    updateStatusUI(response.current_status);
                    if (response.current_status === 'CONNECTED') {
                        $qrContainer.hide(); // Oculta QR si está conectado
                        $qrImage.attr('src', '');
                        $qrLoadingMessage.hide();
                        showStatusMessage(i18n.messageSentSuccess, 'success'); // Reutiliza el mensaje de éxito
                    } else if (response.current_status === 'QRCODE' && response.data.data && response.data.data.qrCodeUrl) {
                        // Si el estado es QRCODE y n8n envía la URL del QR de vuelta
                        $qrImage.attr('src', response.data.data.qrCodeUrl).show();
                        $qrContainer.show();
                        $qrLoadingMessage.text(i18n.qrGeneratedScan).show();
                        showStatusMessage(i18n.qrGeneratedScan, 'warning'); // Muestra mensaje de advertencia para escanear
                    } else {
                        $qrImage.hide().attr('src', '');
                        $qrContainer.show(); // Muestra el contenedor para explicar
                        $qrLoadingMessage.hide();
                        $qrMessage.text(i18n.notConnectedGenerateQr).show();
                        showStatusMessage(i18n.notConnectedGenerateQr, 'warning');
                    }
                } else {
                    showErrorMessage(response.message || i18n.failedToGetStatus);
                    updateStatusUI('error');
                    $qrLoadingMessage.hide();
                }
            },
            error: function(xhr) {
                // Manejar errores de nonce específicos si el servidor los devuelve con un código HTTP 403
                if (xhr.status === 403 && xhr.responseJSON && xhr.responseJSON.code === 'rest_nonce_invalid') {
                    showErrorMessage(i18n.error + ' ' + xhr.responseJSON.message + ' ' + i18n.refreshPage); // Opcional: i18n.refreshPage
                } else {
                    showErrorMessage(xhr.responseJSON ? xhr.responseJSON.message : i18n.error + ' ' + i18n.failedToGetStatus);
                }
                updateStatusUI('error');
                $qrLoadingMessage.hide();
            }
        });
    }

    // Evento al hacer clic en el botón Generar QR
    $('#get_qr_button').on('click', function(e) {
        e.preventDefault();
        hideErrorMessage();
        hideStatusMessage();
        $qrMessage.hide();
        $qrImage.hide().attr('src', ''); // Limpiar QR anterior
        $qrLoadingMessage.text(i18n.generatingQr).show();
        updateStatusUI('pending_qr_scan');

        $.ajax({
            url: api_url + 'qr',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                if (response.success && response.data && response.data.data && (response.data.data.qrCodeUrl || response.data.data.qrCodeImageBase64)) {
                    var qrData = response.data.data.qrCodeUrl || response.data.data.qrCodeImageBase64;
                    $qrImage.attr('src', qrData).show();
                    $qrContainer.show();
                    $qrLoadingMessage.text(i18n.qrGeneratedScan);
                    showStatusMessage(i18n.qrGeneratedScan, 'info');

                    // Iniciar un intervalo para verificar el estado de la sesión después de generar el QR
                    // Esto es útil porque el QR tiene un tiempo de vida.
                    setTimeout(checkStatus, 10000); // Revisa el estado después de 10 segundos
                } else {
                    showErrorMessage(response.message || i18n.failedToGenerateQr);
                    $qrLoadingMessage.hide();
                    updateStatusUI('error');
                }
            },
            error: function(xhr) {
                if (xhr.status === 403 && xhr.responseJSON && xhr.responseJSON.code === 'rest_nonce_invalid') {
                    showErrorMessage(i18n.error + ' ' + xhr.responseJSON.message + ' ' + i18n.refreshPage);
                } else {
                    showErrorMessage(xhr.responseJSON ? xhr.responseJSON.message : i18n.error + ' ' + i18n.failedToGenerateQr);
                }
                $qrLoadingMessage.hide();
                updateStatusUI('error');
            }
        });
    });

    // Evento al hacer clic en el botón Verificar Estado
    $('#check_status_button').on('click', checkStatus);

    // Evento al hacer clic en el botón Enviar Mensaje de Prueba
    $('#send_test_message_button').on('click', function(e) {
        e.preventDefault();
        hideErrorMessage();
        hideStatusMessage();
        $('#test_message_response').empty();

        var to = $('#test_message_to').val();
        var message = $('#test_message_content').val();

        if (!to || !message) {
            showErrorMessage(i18n.requiredFields);
            return;
        }

        // **Validación de número de teléfono en el cliente**
        if (!isValidPhoneNumber(to)) {
            showErrorMessage(i18n.invalidNumber);
            return;
        }

        $('#test_message_response').html('<p class="dokan-text-info">' + i18n.sendingMessage + '</p>');

        $.ajax({
            url: api_url + 'enviar-mensaje',
            method: 'POST',
            data: {
                to: to,
                message: message
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                if (response.success) {
                    $('#test_message_response').html('<p class="dokan-text-success">' + i18n.messageSentSuccess + ' ' + JSON.stringify(response.data) + '</p>');
                } else {
                    $('#test_message_response').html('<p class="dokan-text-danger">' + i18n.failedToSendMessage + ' ' + (response.message || i18n.error + '.') + '</p>');
                }
            },
            error: function(xhr) {
                if (xhr.status === 403 && xhr.responseJSON && xhr.responseJSON.code === 'rest_nonce_invalid') {
                    showErrorMessage(i18n.error + ' ' + xhr.responseJSON.message + ' ' + i18n.refreshPage);
                } else {
                    $('#test_message_response').html('<p class="dokan-text-danger">' + i18n.error + ': ' + (xhr.responseJSON ? xhr.responseJSON.message : 'An unknown error occurred.') + '</p>');
                }
            }
        });
    });

    // Realizar una comprobación de estado inicial al cargar la página
    checkStatus();
});
