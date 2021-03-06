if("undefined"==typeof jQuery) {
    throw new Error("Payum's JavaScript requires jQuery");
}

Payum = function(serverUrl) {
    var payum = this;

    payum.serverUrl = serverUrl;

    payum.payment = {
        create: function(amount, currency, callback) {
            $.ajax(payum.serverUrl + '/payments', {
                'data': JSON.stringify({'totalAmount': amount, 'currencyCode': currency}),
                'type': 'POST',
                'processData': false,
                'contentType': 'application/json',
                success: function(data) {
                    callback(data.payment);
                }
            });
        },
        get: function(id, callback) {
            $.ajax(payum.serverUrl + '/payments/'+id, {
                'type': 'GET',
                success: function(data) {
                    callback(data.payment);
                }
            });
        }
    };

    payum.token = {
        create: function(type, paymentId, afterUrl, callback) {
            $.ajax(payum.serverUrl + '/tokens', {
                'data': JSON.stringify({'type': type, 'paymentId': paymentId, 'afterUrl': afterUrl}),
                'type': 'POST',
                'processData': false,
                'contentType': 'application/json',
                success: function(data) {
                    callback(data.token);
                }
            });
        },
        get: function(id, callback) {
            $.get(payum.serverUrl + '/payments/'+id, {success: callback});
        }
    };

    payum.execute = function(url, container) {
        jQuery.ajax(url, {
            type: "GET",
            async: true,
            headers: {
                Accept: 'application/vnd.payum+json'
            },
            complete: function(data) {
                payum.updateContainer(data, container);

                $(container + ' form').on('submit', function (e) {
                    e.preventDefault();

                    var form = $(this);

                    var values = {};
                    $.each(form.serializeArray(), function (i, field) {
                        values[field.name] = field.value;
                    });

                    jQuery.ajax(form.attr('action'), {
                        type: "POST",
                        headers: {
                            Accept: 'application/vnd.payum+json'
                        },
                        data: values,
                        success: function(data) {
                        },
                        complete: function(data) {
                            payum.updateContainer(data, container);
                        },
                        error: function() {
                        }
                    });
                });
            }
        });
    };

    payum.updateContainer = function updateContainer(data, container) {
        if (data.status == 302) {
            window.location.replace(data.responseJSON.headers.Location);
        }
        if (data.status >= 200 && data.status < 300) {
            $(container).html(data.responseJSON.content);
        }
    };
};