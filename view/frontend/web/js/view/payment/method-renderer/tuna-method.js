let tunaLib;
if (window.checkoutConfig.payment.tunagateway.useSandboxBundle)
    tunaLib = "tuna_essential_sandbox";
else
    tunaLib = "tuna_essential";

define(
    [
        tunaLib,
        'tuna_checkout_info',
        'Magento_Ui/js/modal/alert',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order',
        'Magento_Catalog/js/price-utils'
    ],
    function (tuna_essential, tuna_checkout_info, alert, $, quote, Component, setPaymentInformationAction, placeOrder, priceUtils) {
        'use strict';
        require(['jquery', 'jquery_mask'], function ($) {

            var CpfCnpjMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length <= 11 ? '000.000.000-009' : '00.000.000/0000-00';
            },
                cpfCnpjpOptions = {
                    onKeyPress: function (val, e, field, options) {
                        field.mask(CpfCnpjMaskBehavior.apply({}, arguments), options);
                    }
                };

            $('#tuna_credit_card_document').mask(CpfCnpjMaskBehavior, cpfCnpjpOptions);

            $("#tuna_credit_card_code").mask("999");
            $(".CcCvv").mask("999");
            $("#tuna_credit_card_number").mask("9999 9999 9999 9999");
            $("#tuna_billing_address_zip").mask("99.999-999");

            $('#tuna_billing_address_phone').mask('(00) 0000-00009');

            $("#tuna_billing_address_phone").live("blur", function (event) {
                if ($(this).val().length == 15) {
                    $('#tuna_billing_address_phone').mask('(00) 00000-0009');
                } else {
                    $('#tuna_billing_address_phone').mask('(00) 0000-00009');
                }
            }); 
            $(document).on('change', 'input[name$="[qty]"]', function(){
                var deferred = $.Deferred();
                getTotalsAction([], deferred);
            });           
        });

        $('input[type=radio][name=billingAddress]').live("change", function () {
            $("#billingAddressFields").hide();
        });

        function cardRadioChanged() {
            if ($("#tuna_card_radio_saved").prop("checked")) {
                $("#creditCardPaymentDiv").show();
                $("#newCardDiv").hide();
                $("#lblHolderNameBoleto").hide();
                $("#lblHolderNameCard").show();
                $("#savedCardDiv").show();
                $("#boletoDiv").hide();
                $('#tuna_credit_card_document').show();
                $('#cpfCnpjDiv').show();
                $("#cryptoDiv").hide();
                $("#pixDiv").hide();
                $(".checkout").html("Pagar");
                $('#tuna_credit_card_installments').prop('selectedIndex', 1);
                refreshOrderInfo();
            } else if ($("#tuna_card_radio_new").prop("checked")) {
                $("#savedCardDiv").hide();
                $("#creditCardPaymentDiv").show();
                $("#lblHolderNameBoleto").hide();
                $("#lblHolderNameCard").show();
                $("#newCardDiv").show();
                $("#boletoDiv").hide();
                $('#tuna_credit_card_document').show();
                $('#cpfCnpjDiv').show();
                $("#cryptoDiv").hide();
                $("#pixDiv").hide();
                $(".checkout").html("Pagar");
                $('#tuna_credit_card_installments').prop('selectedIndex', 1);
                refreshOrderInfo();
            } else if ($("#tuna_crypto_radio").prop("checked")) {
                $("#savedCardDiv").hide();
                $("#creditCardPaymentDiv").hide();
                $("#lblHolderNameBoleto").hide();
                $("#lblHolderNameCard").hide();
                $('#tuna_credit_card_document').hide();
                $('#cpfCnpjDiv').hide();
                $("#newCardDiv").hide();
                $("#cryptoDiv").show();
                $("#pixDiv").hide();
                $("#boletoDiv").hide();
                $(".checkout").html("Pagar");
                resetOrderInfo();
            } else if ($("#tuna_pix_radio").prop("checked")) {
                $("#savedCardDiv").hide();
                $("#creditCardPaymentDiv").hide();
                $("#lblHolderNameBoleto").hide();
                $("#lblHolderNameCard").hide();
                $('#tuna_credit_card_document').hide();
                $('#cpfCnpjDiv').hide();
                $("#newCardDiv").hide();
                $("#cryptoDiv").hide();
                $("#pixDiv").show();
                $("#boletoDiv").hide();
                $(".checkout").html("Pagar");
                resetOrderInfo();
            } else {
                $("#creditCardPaymentDiv").hide();
                $("#lblHolderNameCard").hide();
                $('#cpfCnpjDiv').show();
                $("#lblHolderNameBoleto").show();
                $('#tuna_credit_card_document').show();
                $("#cryptoDiv").hide();
                $("#pixDiv").hide();
                $("#boletoDiv").show();
                $(".checkout").html("Gerar boleto");
                resetOrderInfo();
            }
        };

        $("#tuna_card_radio_new").live("change", cardRadioChanged);
        $("#tuna_card_radio_saved").live("change", cardRadioChanged);
        $("#tuna_boleto_radio").live("change", cardRadioChanged);
        $("#tuna_crypto_radio").live("change", cardRadioChanged);
        $("#tuna_pix_radio").live("change", cardRadioChanged);
        $('#tuna').live("change", () => { 
          $("#tuna_credit_card_installments").prop('selectedIndex', 1);
          refreshOrderInfo();
        });
        $("#checkmo").live("click", resetOrderInfo);
        $("#tuna_credit_card_installments").live("change", () => {
          if ($("#tuna").prop("checked")) {
              refreshOrderInfo();
          } else {
              resetOrderInfo();
          }
        });

        function valid_credit_card(value) {
            // Accept only digits, dashes or spaces
              if (/[^0-9-\s]+/.test(value)) return false;
          
              // The Luhn Algorithm. It's so pretty.
              let nCheck = 0, bEven = false;
              value = value.replace(/\D/g, "");
          
              for (var n = value.length - 1; n >= 0; n--) {
                  var cDigit = value.charAt(n),
                        nDigit = parseInt(cDigit, 10);
          
                  if (bEven && (nDigit *= 2) > 9) nDigit -= 9;
          
                  nCheck += nDigit;
                  bEven = !bEven;
              }
          
              return (nCheck % 10) == 0;
        }

        // TODO: remove!?
        function isEquivalent(a, b) {
            var aProps = Object.getOwnPropertyNames(a);
            var bProps = Object.getOwnPropertyNames(b);

            if (aProps.length != bProps.length) {
                return false;
            }

            for (var i = 0; i < aProps.length; i++) {
                var propName = aProps[i];

                if (a[propName] !== b[propName]) {
                    return false;
                }
            }

            return true;
        }

        function getValorTotal(valorTotal, juros)
        {
            return (valorTotal * (1 + juros));
        }

        function getValorParcela(valorTotal, parcela, juros)
        {
            return (valorTotal * (1 + juros))/parcela;
        }

        $("#tuna_billing_address_country").live("change", _ => {
            // Fix unknown info
            if ($("#control").length == 1) {
                $("#control").remove();
                $("#tuna_billing_address_country").val("BR");
            }

            let selectCountryID = $("#tuna_billing_address_country option:selected").val();
            let countryRegions = window.checkoutConfig.countries.find(c => c.id == selectCountryID).regions;
            $('#tuna_billing_address_state').find('option').remove();
            countryRegions.forEach(region => {
                let option = new Option(region.name, region.id);
                $('#tuna_billing_address_state').append(option);
            });
        });

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/tuna',
            }, 
            afterRender: function () {
                $('#tuna').prop('checked', true);
                $("#lblTunaPaymentTitle").html(window.checkoutConfig.payment.tunagateway.title);
                if (!window.checkoutConfig.payment.tunagateway.sessionid) {
                    if (!this.allowBoleto()) {
                        $("#tuna_payment_method_content").remove();
                        $("#badNewsDiv").show();
                        return;
                    } else {
                        $("#creditCardPaymentDiv").remove();
                        $("#tuna_savedCard_label").remove();
                        $("#tuna_newCard_label").remove();
                        $("#cryptoDiv").remove();
                        $("#pixDiv").remove();
                        $("#tuna_boleto_radio").prop("checked", true);
                        $("#boletoDiv").show();
                        return;
                    }
                }

                if (!this.allowBoleto()) {
                    $("#tuna_boleto_label").remove();
                    $("#boletoDiv").remove();
                }
                if (!this.allowCrypto()) {
                    $("#tuna_crypto_label").remove();
                    $("#cryptoDiv").remove();
                }
                if (!this.allowPix()) {
                    $("#tuna_pix_label").remove();
                    $("#pixDiv").remove();
                }
                if (this.getStoredCreditCards() && this.getStoredCreditCards().length > 0) {
                    $("#tuna_card_radio_saved").prop("checked", true);
                    $("#savedCardDiv").show();
                } else {
                    $("#tuna_savedCard_label").remove();
                    $("#tuna_card_radio_new").prop("checked", true);
                    $("#newCardDiv").show();
                }
            },
            enableBillingAddressFields: function () {
                $("#billingAddressFields").show();
                $("input[name='billingAddress']").prop("checked", false);
            },
            hideBillingAddressFields: function () {
                $('#billingAddressFields').hide();
            },
            allowBoleto: function () {
                return window.checkoutConfig.payment.tunagateway.allow_boleto &&
                    window.checkoutConfig.payment.tunagateway.allow_boleto === "1";
            },
            allowCrypto: function () {
                return window.checkoutConfig.payment.tunagateway.allow_crypto &&
                    window.checkoutConfig.payment.tunagateway.allow_crypto === "1";
            },
            allowPix: function () {
                return window.checkoutConfig.payment.tunagateway.allow_pix &&
                    window.checkoutConfig.payment.tunagateway.allow_pix === "1";
            },
            getStoredCreditCards: function () {
                return window.checkoutConfig.payment.tunagateway.savedCreditCards;
            },
            getBillingAddresses: function () {
                return window.checkoutConfig.payment.tunagateway.billingAddresses;
            },
            getCountries: function () {
                return window.checkoutConfig.countries;
            },
            getCreditCardFlag: function (brand) {
                return window.tunaImages[brand.toUpperCase()];
            },
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            selectStoredCard: function (cc) {
                $(".CcCvv").hide();
                $("#tuna_card_cvv_" + cc.token).show();
            },
            getMonthsValues: function () {
                return _.map(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'], function (value, key) {
                    return {
                        'value': key + 1,
                        'month': value
                    };
                });
            },
            getYearsValues: function () {
                let thisYear = (new Date()).getFullYear();
                let maxYear = thisYear + 20;
                let years = [];
                for (let i = thisYear; i < maxYear; i++) {
                    years.push(i);
                }

                return _.map(years, function (value, key) {
                    return {
                        'value': value,
                        'year': value
                    };
                });
            },
            getInstallments: function () {
                let feeList = window.checkoutConfig.payment.tunagateway.feeList;
                let installmentIndex = 0;
                return _.map(feeList, function (value, key) {
                    installmentIndex++;
                    let finalText = '';
                    if (value*1 === 0){
                        let parcelValue = parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10)/installmentIndex;
                        let orderTotal = parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10);
                        finalText = installmentIndex + 'x ' + priceUtils.formatPrice(parcelValue) + ' (' + priceUtils.formatPrice(orderTotal) + ')';
                    } else
                    {
                        let fee =(value*1)/100.00;
                        let parcelValue = getValorParcela(parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10), installmentIndex, fee);
                        let oldOrderTotal = parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10);
                        let newOrderTotal = getValorTotal(oldOrderTotal, fee);
                        finalText = installmentIndex + 'x ' + priceUtils.formatPrice(parcelValue) + ' (' + priceUtils.formatPrice(newOrderTotal) + ')';
                    }

                    return {
                        'value': installmentIndex,
                        'text': finalText
                    };
                });
            },
            onlyNumbers: function (value) {
                return value.replace(/\D/g, '');
            },
            isCPFValid: function (cpf) {
                cpf = cpf.replace(/[^\d]+/g, '');
                if (cpf == '') return false;
                if (cpf.length != 11 ||
                    cpf == "00000000000" ||
                    cpf == "11111111111" ||
                    cpf == "22222222222" ||
                    cpf == "33333333333" ||
                    cpf == "44444444444" ||
                    cpf == "55555555555" ||
                    cpf == "66666666666" ||
                    cpf == "77777777777" ||
                    cpf == "88888888888" ||
                    cpf == "99999999999")
                    return false;
                let add = 0;
                for (let i = 0; i < 9; i++)
                    add += parseInt(cpf.charAt(i)) * (10 - i);
                let rev = 11 - (add % 11);
                if (rev == 10 || rev == 11)
                    rev = 0;
                if (rev != parseInt(cpf.charAt(9)))
                    return false;
                add = 0;
                for (let i = 0; i < 10; i++)
                    add += parseInt(cpf.charAt(i)) * (11 - i);
                rev = 11 - (add % 11);
                if (rev == 10 || rev == 11)
                    rev = 0;
                if (rev != parseInt(cpf.charAt(10)))
                    return false;
                return true;
            },
            isCNPJValid: function (cnpj) {

                cnpj = cnpj.replace(/[^\d]+/g, '');

                if (cnpj == '') return false;

                if (cnpj.length != 14)
                    return false;

                if (cnpj == "00000000000000" ||
                    cnpj == "11111111111111" ||
                    cnpj == "22222222222222" ||
                    cnpj == "33333333333333" ||
                    cnpj == "44444444444444" ||
                    cnpj == "55555555555555" ||
                    cnpj == "66666666666666" ||
                    cnpj == "77777777777777" ||
                    cnpj == "88888888888888" ||
                    cnpj == "99999999999999")
                    return false;

                let tamanho = cnpj.length - 2
                let numeros = cnpj.substring(0, tamanho);
                let digitos = cnpj.substring(tamanho);
                let soma = 0;
                let pos = tamanho - 7;
                for (let i = tamanho; i >= 1; i--) {
                    soma += numeros.charAt(tamanho - i) * pos--;
                    if (pos < 2)
                        pos = 9;
                }
                let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
                if (resultado != digitos.charAt(0))
                    return false;

                tamanho = tamanho + 1;
                numeros = cnpj.substring(0, tamanho);
                soma = 0;
                pos = tamanho - 7;
                for (i = tamanho; i >= 1; i--) {
                    soma += numeros.charAt(tamanho - i) * pos--;
                    if (pos < 2)
                        pos = 9;
                }
                resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
                if (resultado != digitos.charAt(1))
                    return false;

                return true;

            },
            endOrder: function (self, creditCardData, paymentData, messageContainer, isBoleto = false, isPix = false, isCrypto = false) {

                let additionalData = {
                    'buyer_document': $('#tuna_credit_card_document').val(),
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_token': isBoleto || isPix || isCrypto ? "" : creditCardData.token,
                    'credit_card_brand':  isBoleto || isPix || isCrypto ? "" : creditCardData.brand,
                    'credit_card_expiration_month':  isBoleto || isPix || isCrypto ? "" : creditCardData.expirationMonth,
                    'credit_card_expiration_year':  isBoleto || isPix || isCrypto ? "" : creditCardData.expirationYear,
                    'credit_card_installments':  isBoleto || isPix || isCrypto ? "1" : ($('#tuna_credit_card_installments').val() || "1" ),
                    'buyer_name': isBoleto ? $('#tuna_boleto_holder').val() : isPix || isCrypto ? "" : creditCardData.cardHolderName,
                    'is_boleto_payment': isBoleto ? "true" : "false",
                    'is_crypto_payment': isCrypto ? "true" : "false",
                    'is_pix_payment': isPix ? "true" : "false",
                    'payment_type': isBoleto ? "TUNA_EBOL" : isPix ? "TUNA_EPIX" : isCrypto ? "TUNA_ECRYPTO" : "TUNA_ECAC"
                };
                if (Object.prototype.hasOwnProperty.call(paymentData, '__disableTmpl')) { delete paymentData.__disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'disableTmpl')) { delete paymentData.disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'title')) { delete paymentData.title; }
                $.when(setPaymentInformationAction(messageContainer, {
                    'method': this.getCode(),
                    'additional_data': additionalData
                })).done(function () {
                    delete paymentData['title'];
                    $.when(
                        placeOrder(paymentData, messageContainer)).done(function () {
                            $.mage.redirect(window.checkoutConfig.tuna_payment);
                        });
                }).fail(function () {
                    alert({
                        title: $.mage.__('Algo deu errado'),
                        content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                    });
                }).always(function () {
                    
                    // fullScreenLoader.stopLoader();
                });
            },
            isUsingSavedCard: function () {
                return $("#tuna_card_radio_saved").prop("checked");
            },
            isUsingNewCard: function () {
                return $("#tuna_card_radio_new").prop("checked");
            },
            isBoletoPayment: function () {
                return $("#tuna_boleto_radio").prop("checked");
            },
            isCryptoPayment: function () {
                return $("#tuna_crypto_radio").prop("checked");
            },
            isPixPayment: function () {
                return $("#tuna_pix_radio").prop("checked");
            },
            getSelectedCardToken: function () {
                return $("input[name='storedCard']:checked").attr("id").substring(10, $("input[name='storedCard']:checked").attr("id").length);
            },
            isFieldsValid: function () {

                if (this.isUsingNewCard()) {

                    if (!$('#tuna_credit_card_holder')[0].value)
                        return "holderInvalidInfo";

                    let cardNumber = this.onlyNumbers($('#tuna_credit_card_number')[0].value);
                    if (!cardNumber || !valid_credit_card(cardNumber) )
                        return "creditCardInvalidInfo";

                    let expirationYear = $('#tuna_credit_card_expiration_year')[0].value;
                    let expirationMonth = $('#tuna_credit_card_expiration_month')[0].value;

                    if (!expirationMonth || !expirationYear)
                        return "validityDateInvalidInfo";

                    let expireDate = new Date(expirationYear, parseInt(expirationMonth) - 1);
                    if (expireDate < new Date())
                        return "validityDateInvalidInfo";

                    if (!$("#tuna_credit_card_code")[0].value || $("#tuna_credit_card_code")[0].value < 3)
                        return "cvvInvalidInfo";

                } else if (this.isUsingSavedCard()) {
                    if ($("input[name='storedCard']:checked").length > 0) {
                        if (!$("#tuna_card_cvv_" + this.getSelectedCardToken()).val())
                            return "cvvSavedInvalidInfo";
                    } else
                        return "noCreditCardSelected";
                } else if (this.isBoletoPayment()) {
                    if (!$('#tuna_boleto_holder')[0].value)
                        return "boletoHolderInvalidInfo";
                } else if (this.isCryptoPayment()) {                    
                } else if (this.isPixPayment()) {                    
                } else {
                    return "error";
                }

                let document = $('#tuna_credit_card_document')[0].value;
                const hasDocument = !!document;
                const hasDocumentCheck = !this.isPixPayment() && !this.isCryptoPayment();
                const isValidDocument = this.isCNPJValid(document) || this.isCPFValid(document);
                if (hasDocumentCheck && !(hasDocument && isValidDocument)) {
                    return "cpfInvalidInfo";
                }

                return null;
            },
            placeOrder: async function () {
                if ($("#customer-email").val() == "" && $(".authentication-wrapper").css("display") == "block") {
                    alert({
                        title: $.mage.__('Algo deu errado'),
                        content: $.mage.__('Operação só pode ser realizada após o preenchimento do campo e-mail.')
                    });
                    return false;
                }
                let self = this;
                let paymentData = quote.paymentMethod();
                let messageContainer = this.messageContainer;
                let fieldCheckResponse = this.isFieldsValid();
                if (!fieldCheckResponse) {
                    let tunaLib = Tuna(window.checkoutConfig.payment.tunagateway.sessionid);
                    let tokenizator = tunaLib.tokenizator();

                    if (this.isUsingSavedCard()) {                        
                        let creditCardData = this.getStoredCreditCards().find(cc => cc.token === this.getSelectedCardToken());
                        let creditCardCvv = $(".CcCvv:visible").val();
                        let bindResponse = await tokenizator.bind(creditCardData.token, creditCardCvv);

                        if (bindResponse && bindResponse.code == 1)
                            self.endOrder(self, creditCardData, paymentData, messageContainer);
                        else {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }

                    } else if (this.isBoletoPayment()) {                        
                        self.endOrder(self, null, paymentData, messageContainer, true, false, false);
                    } else if (this.isCryptoPayment()) { 
                        self.endOrder(self, null, paymentData, messageContainer, false, false, true);
                    } else if (this.isPixPayment()) {                        
                        self.endOrder(self, null, paymentData, messageContainer, false, true, false);
                    } else {                        
                        let cardData = {
                            CardHolderName: $('#tuna_credit_card_holder').val(),
                            CardNumber: this.onlyNumbers($('#tuna_credit_card_number').val()),
                            ExpirationMonth: parseInt($('#tuna_credit_card_expiration_month').val()),
                            ExpirationYear: parseInt($('#tuna_credit_card_expiration_year').val()),
                            Cvv: $('#tuna_credit_card_code').val()
                        };

                        let generateResponse = await tokenizator.generate(cardData);

                        if (generateResponse.code == 1) {
                            let creditCardData = {
                                token: generateResponse.token,
                                brand: generateResponse.brand,
                                expirationMonth: cardData.ExpirationMonth,
                                expirationYear: cardData.ExpirationYear,
                                cardHolderName: cardData.CardHolderName
                            };
                            self.endOrder(self, creditCardData, paymentData, messageContainer);
                        }
                        else {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }
                    }
                } else {                    
                    let validationLabels = ["holderInvalidInfo", "cpfInvalidInfo", "cvvSavedInvalidInfo",
                        "boletoHolderInvalidInfo", "creditCardInvalidInfo", "validityDateInvalidInfo",
                        "cvvInvalidInfo", "noCreditCardSelected", "installmentsInvalidInfo"];

                    validationLabels.forEach(fieldID => {
                        $("#" + fieldID).hide();
                    });

                    $("#" + fieldCheckResponse).show();
                }
            }
        });
    }
);
