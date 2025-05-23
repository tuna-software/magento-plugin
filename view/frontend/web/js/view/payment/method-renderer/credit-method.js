define(
    [
        'tuna_lib',
        'Magento_Ui/js/modal/alert',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order',
        'Magento_Catalog/js/price-utils'
    ],
    function (t, alert, $, quote, Component, setPaymentInformationAction, placeOrder, priceUtils) {
        'use strict';

        function fillInstallmentsSelector() {
            const firstCreditCardValue = parseFloat($(" #tuna_first_credit_card_value").val().replace(',', '.')).toFixed(2);
            const secondCreditCardValue = parseFloat($(" #tuna_second_credit_card_value").val().replace(',', '.')).toFixed(2);

            $(' #tuna_first_credit_card_installments').empty();
            $.each(getInstallments(firstCreditCardValue), function (i, item) {
                $(' #tuna_first_credit_card_installments').append($('<option>', {
                    value: item.value,
                    text: item.text
                }));
            });
            $(' #tuna_second_credit_card_installments').empty();
            $.each(getInstallments(secondCreditCardValue), function (i, item) {
                $(' #tuna_second_credit_card_installments').append($('<option>', {
                    value: item.value,
                    text: item.text
                }));
            });
            refreshOrderInfo();
        }

        function onPayWithTwoCardsValuesChanges(cardValueIndex) {
            resetOrderInfo();
            const cartTotal = getOrderTotal();
            let changedFieldID, otherFieldID;

            if (cardValueIndex == 1) {
                changedFieldID = " #tuna_first_credit_card_value";
                otherFieldID = " #tuna_second_credit_card_value";
            } else {
                changedFieldID = " #tuna_second_credit_card_value";
                otherFieldID = " #tuna_first_credit_card_value";
            }

            if (parseFloat($(changedFieldID).val()) >= cartTotal) {
                const valuePerCard = (cartTotal / 2).toFixed(2);
                $(otherFieldID).val(valuePerCard.toString().replace('.', ','));
                $(changedFieldID).val((valuePerCard * 2 == cartTotal ? valuePerCard : valuePerCard + 0.01).toString().replace('.', ','));
            } else {
                const valueDiff = parseFloat($(changedFieldID).val().replace(',', '.'));
                $(otherFieldID).val((cartTotal - valueDiff).toFixed(2).toString().replace('.', ','));
                $(changedFieldID).val(parseFloat($(changedFieldID).val().replace(',', '.')).toFixed(2).toString().replace('.', ','));
            }
            fillInstallmentsSelector();
        }

        function getInstallments(value) {
            let feeList = window.checkoutConfig.payment.tunagateway.feeList;
            const referenceValue = value || getOrderTotal();

            const getValorTotal = (valorTotal, juros) => {
                return (valorTotal * (1 + juros));
            };

            const getValorParcela = (valorTotal, parcela, juros) => {
                return (valorTotal * (1 + juros)) / parcela;
            };

            let installmentIndex = 0;
            let result = [];
            feeList.forEach(value => {
                installmentIndex++;
                let finalText = '';
                let installmentValue, totalValue;
                if (value * 1 === 0) {
                    installmentValue = referenceValue / installmentIndex;
                    totalValue = referenceValue;
                } else {
                    let fee = (value * 1) / 100.00;
                    installmentValue = getValorParcela(referenceValue, installmentIndex, fee);
                    totalValue = getValorTotal(referenceValue, fee);
                }

                const minimumInstallmentValue = parseFloat(window.checkoutConfig.payment.tunagateway.minimum_installment_value ?? -1);

                if (minimumInstallmentValue > 0 && installmentIndex !== 1 && installmentValue < minimumInstallmentValue)
                    return;

                finalText = installmentIndex + 'x ' + priceUtils.formatPrice(installmentValue) + ' (' + priceUtils.formatPrice(totalValue) + ')';
                result.push({
                    'value': installmentIndex,
                    'text': finalText
                });
            });

            return result;
        }

        function secondCardRadioChanged() {
            if ($("#tuna_second_card_radio_saved").prop("checked")) {
                $("#secondNewCardDiv").hide();
                $("#secondSavedCardDiv").show();
            } else {
                $("#secondSavedCardDiv").hide();
                $("#secondNewCardDiv").show();
            }
        };

        function firstCardRadioChanged() {
            if ($("#tuna_first_card_radio_saved").prop("checked")) {
                $("#newCardDiv").hide();
                $("#savedCardDiv").show();
            } else {
                $("#savedCardDiv").hide();
                $("#newCardDiv").show();
            }
        };

        const payUsingTwoCardsClicked = function () {
            if ($(" #payingWithTwoCards").val() === "0") {
                // Pay with two cards
                const savedCards = window.checkoutConfig.payment.tunagateway.savedCreditCards;
                $(" #secondCardSelectorDiv").show();
                if (!savedCards || savedCards.length == 0) {
                    $(" #tuna_second_savedCard_label").remove();
                    $(" #tuna_second_new_card_radio").prop("checked", true);
                    $(" #secondNewCardDiv").show();
                } else {
                    $(" #tuna_second_card_radio_saved").prop("checked", true);
                    $(" #secondSavedCardDiv").show();
                }
                $(" #payingWithTwoCards").val("1");
                $(" #payUsingTwoCardsLink").text("Pagar usando um cartão");
                $(" #installmentsDiv").hide();

                const valuePerCard = (getOrderTotal() / 2).toFixed(2);
                $(" #tuna_second_credit_card_value").val(valuePerCard.toString().replace('.', ','));
                $(" #firstCardValueDiv").show();
                $(" #first_card_installments_div").show();
                $(" #tuna_first_credit_card_value").val((valuePerCard * 2 == getOrderTotal() ? valuePerCard : valuePerCard + 0.01).toString().replace('.', ','));

                fillInstallmentsSelector();
            } else {
                // Pay with one card
                $(" #payingWithTwoCards").val("0");
                $(" #installmentsDiv").show();
                $(" #first_card_installments_div").hide();
                $(" #secondCardSelectorDiv").hide();
                $(" #firstCardValueDiv").hide();
                $(" #payUsingTwoCardsLink").text("Pagar usando dois cartões");
                resetOrderInfo();
            }
        };

        function getOrderTotal() {
            return parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10);
        }

        function addMasks() {
            const CpfCnpjMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length <= 11 ? '000.000.000-009' : '00.000.000/0000-00';
            };
            const cpfCnpjpOptions = {
                onKeyPress: function (val, e, field, options) {
                    field.mask(CpfCnpjMaskBehavior.apply({}, arguments), options);
                }
            };
            try {
                $(' #tuna_credit_card_document').mask(CpfCnpjMaskBehavior, cpfCnpjpOptions);
                $(" #tuna_first_credit_card_value").mask("9999999999,99");
                $(" #tuna_second_credit_card_value").mask("999999999,99");
                $(" #tuna_credit_card_code").mask("999");
                $(" #tuna_second_credit_card_code").mask("999");
                $(" CcCvv").mask("999");
                $(" #tuna_credit_card_number").mask("9999 9999 9999 9999");
                $(" #tuna_second_credit_card_number").mask("9999 9999 9999 9999");
                $(" #tuna_billing_address_zip").mask("99.999-999");

                $(' #tuna_billing_address_phone').mask('(00) 0000-00009');

                $(document).on("blur", " #tuna_billing_address_phone", function (event) {
                    if ($(this).val().length == 15) {
                        $(' #tuna_billing_address_phone').mask('(00) 00000-0009');
                    } else {
                        $(' #tuna_billing_address_phone').mask('(00) 0000-00009');
                    }
                });
            } catch (e) {
                console.error(e.message);
            }
        }

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

        function addEvents() {
            try {
                $("#tuna_first_credit_card_value").on("blur", function () {
                    onPayWithTwoCardsValuesChanges(1);
                });
                $("#tuna_second_credit_card_value").on("blur", function () {
                    onPayWithTwoCardsValuesChanges(2);
                });
                $('#tuna_first_credit_card_installments').on("change", function () {
                    refreshOrderInfo();
                });
                $(' #tuna_second_credit_card_installments').on("change", _ => refreshOrderInfo());
                $("#payUsingTwoCardsLink").on("click", payUsingTwoCardsClicked);
                $("#payingWithTwoCards").on("change", payUsingTwoCardsClicked);
                $(" #checkmo").on("click", resetOrderInfo);

                $(" #tuna_billing_address_country").on("change", _ => {
                    // Fix unknown info
                    if ($(" #control").length == 1) {
                        $(" #control").remove();
                        $(" #tuna_billing_address_country").val("BR");
                    }

                    let selectCountryID = $(" #tuna_billing_address_country option:selected").val();
                    let countryRegions = window.checkoutConfig.countries.find(c => c.id == selectCountryID).regions;
                    $(' #tuna_billing_address_state').find('option').remove();
                    countryRegions.forEach(region => {
                        let option = new Option(region.name, region.id);
                        $(' #tuna_billing_address_state').append(option);
                    });
                });

                $(document).on('change', 'input[name$="[qty]"]', function () {
                    var deferred = $.Deferred();
                    getTotalsAction([], deferred);
                });

                $(" #tuna_credit_card_installments").on("change", () => {
                    if ($(" #tuna").prop("checked")) {
                        refreshOrderInfo();
                    } else {
                        resetOrderInfo();
                    }
                });

                $(document).on("change", 'input[id=payingWithTwoCards]', function () {
                    payUsingTwoCardsClicked()
                });

                $('input[type=radio][name=billingAddress]').on("change", function () {
                    $(" #billingAddressFields").hide();
                });

                $(document).on("change", "input[name=secondCardRadio]", function () {
                    secondCardRadioChanged();
                });
                $(document).on("change", "input[name=firstCardRadio]", function () {
                    firstCardRadioChanged();
                });
            } catch (e) {
                console.error(e.message);
            }
        }

        function getOrderTotal() {
            return parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10);
        }

        function getInstallments(value) {
            let feeList = window.checkoutConfig.payment.tunagateway.feeList;
            const referenceValue = value || getOrderTotal();

            let installmentIndex = 0;
            let result = [];
            feeList.forEach(value => {
                installmentIndex++;
                let finalText = '';
                let installmentValue, totalValue;
                if (value * 1 === 0) {
                    installmentValue = referenceValue / installmentIndex;
                    totalValue = referenceValue;
                } else {
                    let fee = (value * 1) / 100.00;
                    installmentValue = (referenceValue * (1 + fee)) / installmentIndex;
                    totalValue = (referenceValue * (1 + fee));
                }

                const minimumInstallmentValue = parseFloat(window.checkoutConfig.payment.tunagateway.minimum_installment_value ?? -1);

                if (minimumInstallmentValue > 0 && installmentIndex !== 1 && installmentValue < minimumInstallmentValue)
                    return;

                finalText = installmentIndex + 'x ' + priceUtils.formatPrice(installmentValue) + ' (' + priceUtils.formatPrice(totalValue) + ')';
                result.push({
                    'value': installmentIndex,
                    'text': finalText
                });
            });

            return result;
        }

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/credit',
            },


            initialize: function () {
                this._super();

                var sessionID = window.checkoutConfig.payment.tunagateway.sessionid;
                var env = window.checkoutConfig.payment.tunagateway.useSandboxBundle ? 'sandbox' : 'production';

                if (sessionID)
                    this.tuna = Tuna(sessionID, env);
            },

            afterRender: function () {
                $("#creditTitle").html(window.checkoutConfig.payment.tunagateway.title_credit);
                if (!this.tuna) {
                    $("#badNewsDiv").show();
                } else {
                    // Hide the saved card radio form if there are no saved cards for first card
                    if (!this.getStoredCreditCards() || this.getStoredCreditCards().length <= 0) {
                        $('#tuna_first_savedCard_label').hide();
                        $('#tuna_first_card_radio_saved').prop('checked', false);
                        $('#tuna_first_new_card_radio').prop('checked', true);
                        $('#newCardDiv').show();
                    }

                    $("#divCreditOnly").one('click', function () {
                        $("button.defaultTunaButton").addClass("action primary checkout");
                    });
                    setTimeout(function () {
                        addEvents();
                        addMasks();
                    }, 500);
                }
            },

            enableBillingAddressFields: function () {
                $(" #billingAddressFields").show();
                $("input[name='billingAddress']").prop("checked", false);
            },
            hideBillingAddressFields: function () {
                $(' #billingAddressFields').hide();
            },
            allowCard: function () {
                return window.checkoutConfig.payment.tunagateway.allow_card &&
                    window.checkoutConfig.payment.tunagateway.allow_card === "1";
            },
            allowPaymentWithTwoCards: function () {
                return window.checkoutConfig.payment.tunagateway.allow_pay_with_two_cards &&
                    window.checkoutConfig.payment.tunagateway.allow_pay_with_two_cards === "1";
            },
            isTunaActive: function () {
                return window.checkoutConfig.payment.tunagateway.tuna_active &&
                    window.checkoutConfig.payment.tunagateway.tuna_active === "1";
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
            selectStoredCard: function (paymentCardIndex, cc) {
                if (paymentCardIndex === 1) {
                    $("input[id^='tuna_card_cvv_']").hide();
                    $(" #tuna_card_cvv_" + cc.token).show();
                } else {
                    $("input[id^='tuna_second_card_cvv_']").hide();
                    $(" #tuna_second_card_cvv_" + cc.token).show();
                }
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
            getInstallments: function (value) {
                return getInstallments(value);
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
                for (let i = tamanho; i >= 1; i--) {
                    soma += numeros.charAt(tamanho - i) * pos--;
                    if (pos < 2)
                        pos = 9;
                }
                resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
                if (resultado != digitos.charAt(1))
                    return false;

                return true;

            },

            isActive: function () {
                let tunaGateway = window?.checkoutConfig?.payment?.tunagateway;

                return tunaGateway?.allow_card === "1" && tunaGateway?.tuna_active !== "1";
            },
            endOrder: function (self, creditCardData, secondCreditCardData, paymentData, messageContainer, isBoleto = false, isPix = false, isCrypto = false, isLink = false) {

                const pushCreditCard = (array, creditCardData) => {
                    array.push({
                        'card_holder_name': creditCardData.cardHolderName,
                        'credit_card_token': creditCardData.token,
                        'credit_card_brand': creditCardData.brand,
                        'credit_card_expiration_month': creditCardData.expirationMonth,
                        'credit_card_expiration_year': creditCardData.expirationYear,
                        'credit_card_installments': creditCardData.installments,
                        'credit_card_amount': creditCardData.amount
                    });
                }
                let creditCards = [];
                if (creditCardData)
                    pushCreditCard(creditCards, creditCardData);
                if (secondCreditCardData)
                    pushCreditCard(creditCards, secondCreditCardData);

                const additionalData = {
                    'buyer_document': $(' #tuna_credit_card_document').val(),
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_data': JSON.stringify(creditCards),
                    'buyer_name': isBoleto ? $(' #tuna_boleto_holder').val() : "",
                    'is_boleto_payment': isBoleto ? "true" : "false",
                    'is_crypto_payment': isCrypto ? "true" : "false",
                    'is_pix_payment': isPix ? "true" : "false",
                    'is_link_payment': isLink ? "true" : "false",
                    'payment_type': isBoleto ? "TUNA_EBOL" : isPix ? "TUNA_EPIX" : isCrypto ? "TUNA_ECRYPTO" : isLink ? "TUNA_ELINK" : "TUNA_ECAC",
                };

                if (Object.prototype.hasOwnProperty.call(paymentData, '__disableTmpl')) {
                    delete paymentData.__disableTmpl;
                }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'disableTmpl')) {
                    delete paymentData.disableTmpl;
                }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'title')) {
                    delete paymentData.title;
                }
                $.when(setPaymentInformationAction(messageContainer, {
                    'method': this.getCode(),
                    'additional_data': additionalData
                })).done(function () {
                    delete paymentData['title'];
                    $.when(
                        placeOrder(paymentData, messageContainer)).done(function () {
                        $.mage.redirect(window.checkoutConfig.tuna_payment);
                    }).fail(function (result) {
                        alert({
                            title: $.mage.__('Ocorreu um erro no processamento'),
                            content: $.mage.__(result.responseJSON.message)
                        });
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
            isUsingFirstSavedCard: function () {
                return $("#tuna_first_card_radio_saved").prop("checked");
            },
            isUsingSecondSavedCard: function () {
                return $("#tuna_second_card_radio_saved").prop("checked");
            },
            isUsingNewFirstCard: function () {
                return $("#tuna_first_new_card_radio").prop("checked");
            },
            isUsingNewSecondCard: function () {
                return $("#tuna_second_new_card_radio").prop("checked");
            },
            isUsingTwoCards: function () {
                return $("#payingWithTwoCards").val() === "1";
            },
            isBoletoPayment: function () {
                return false;
            },
            isCryptoPayment: function () {
                return false;
            },
            isPixPayment: function () {
                return false;
            },
            isLinkPayment: function () {
                return false;
            },
            getSelectedCardToken: function (cardIndex) {
                const fieldname = cardIndex === 1 ? "firstStoredCard" : "secondStoredCard";
                return $(`input[name='${fieldname}']:checked`).attr("id").substring(cardIndex === 1 ? 16 : 17, $(`input[name='${fieldname}']:checked`).attr("id").length);
            },
            validateFirstNewCardData: function () {
                if (!$(' #tuna_credit_card_holder')[0].value)
                    return "holderInvalidInfo";

                let cardNumber = this.onlyNumbers($(' #tuna_credit_card_number')[0].value);
                if (!cardNumber || !valid_credit_card(cardNumber))
                    return "creditCardInvalidInfo";

                let expirationYear = $(' #tuna_credit_card_expiration_year')[0].value;
                let expirationMonth = $(' #tuna_credit_card_expiration_month')[0].value;

                if (!expirationMonth || !expirationYear)
                    return "validityDateInvalidInfo";

                let expireDate = new Date(expirationYear, parseInt(expirationMonth) - 1);
                if (expireDate < new Date(Date.year, Date.month))
                    return "validityDateInvalidInfo";

                if (!$(" #tuna_credit_card_code")[0].value || $(" #tuna_credit_card_code")[0].value < 3)
                    return "cvvInvalidInfo";
            },
            validateSecondNewCardData: function () {
                if (!$(' #tuna_second_credit_card_holder')[0].value)
                    return "secondHolderInvalidInfo";

                let cardNumber = this.onlyNumbers($(' #tuna_second_credit_card_number')[0].value);
                if (!cardNumber || !valid_credit_card(cardNumber))
                    return "secondCreditCardInvalidInfo";

                let expirationYear = $(' #tuna_second_credit_card_expiration_year')[0].value;
                let expirationMonth = $(' #tuna_second_credit_card_expiration_month')[0].value;

                if (!expirationMonth || !expirationYear)
                    return "secondValidityDateInvalidInfo";

                let expireDate = new Date(expirationYear, parseInt(expirationMonth) - 1);
                if (expireDate < new Date(Date.year, Date.month))
                    return "secondValidityDateInvalidInfo";

                if (!$(" #tuna_second_credit_card_code")[0].value || $(" #tuna_second_credit_card_code")[0].value < 3)
                    return "secondCvvInvalidInfo";
            },
            validateFirstSavedCardData: function () {
                if ($("input[name='firstStoredCard']:checked").length > 0) {
                    if (!$("#tuna_card_cvv_" + this.getSelectedCardToken(1)).val())
                        return "cvvSavedInvalidInfo";
                } else
                    return "noCreditCardSelected";
            },
            validateSecondSavedCardData: function () {
                if ($("input[name='secondStoredCard']:checked").length > 0) {
                    if (!$(`#tuna_second_card_cvv_${this.getSelectedCardToken(2)}`).val())
                        return "secondCvvSavedInvalidInfo";
                } else
                    return "secondNoCreditCardSelected";
            },
            isFieldsValid: function () {
                if (this.isUsingTwoCards()) {
                    if (this.isUsingNewFirstCard()) {
                        const response = this.validateFirstNewCardData();
                        if (response)
                            return response;
                    } else if (this.isUsingFirstSavedCard()) {
                        const response = this.validateFirstSavedCardData();
                        if (response)
                            return response;
                    }
                    if (this.isUsingNewSecondCard()) {
                        const response = this.validateSecondNewCardData();
                        if (response)
                            return response;
                    } else if (this.isUsingSecondSavedCard()) {
                        const response = this.validateSecondSavedCardData();
                        if (response)
                            return response;
                    }
                } else if (this.isUsingNewFirstCard()) {
                    const response = this.validateFirstNewCardData();
                    if (response)
                        return response;
                } else if (this.isUsingFirstSavedCard()) {
                    const response = this.validateFirstSavedCardData();
                    if (response)
                        return response;
                } else {
                    return "error";
                }

                let documentValue = $(' #tuna_credit_card_document').val();
                const hasDocument = !!documentValue;
                const isValidDocument = this.isCNPJValid(documentValue) || this.isCPFValid(documentValue);
                if (!(hasDocument && isValidDocument)) {
                    return "cpfInvalidInfo";
                }

                return null;
            },
            getSavedCardDataByIndex: async function (cardIndex) {
                const tokenizator = Tuna(window.checkoutConfig.payment.tunagateway.sessionid).tokenizator();
                const creditCardData = this.getStoredCreditCards().find(cc => cc.token === this.getSelectedCardToken(cardIndex));
                const creditCardCvv = $(`#tuna_${cardIndex == 1 ? 'first' : 'second'}_card_cvv_${creditCardData.token}:visible`).val();
                const bindResponse = await tokenizator.bind(creditCardData.token, creditCardCvv);

                if (bindResponse && bindResponse.code == 1)
                    return creditCardData;
                return null;
            },
            tokenizeCardData: async function (cardData) {
                const tokenizator = Tuna(window.checkoutConfig.payment.tunagateway.sessionid).tokenizator();
                let generateResponse = await tokenizator.generate(cardData);

                if (generateResponse.code == 1)
                    return {
                        token: generateResponse.token,
                        brand: generateResponse.brand,
                        expirationMonth: cardData.ExpirationMonth,
                        expirationYear: cardData.ExpirationYear,
                        cardHolderName: cardData.CardHolderName
                    };
                return null;
            },
            placeOrder: async function () {
                if ($(" #customer-email").val() == "" && $(" authentication-wrapper").css("display") == "block") {
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
                    if (this.isUsingTwoCards()) {

                        let firstCreditCardData, secondCreditCarData;

                        if (this.isUsingFirstSavedCard()) {
                            firstCreditCardData = await this.getSavedCardDataByIndex(1);
                        } else if (this.isUsingNewFirstCard()) {
                            const cardData = {
                                cardHolderName: $(' #tuna_credit_card_holder').val(),
                                cardNumber: this.onlyNumbers($(' #tuna_credit_card_number').val()),
                                expirationMonth: parseInt($(' #tuna_credit_card_expiration_month').val()),
                                expirationYear: parseInt($(' #tuna_credit_card_expiration_year').val()),
                                cvv: $(' #tuna_credit_card_code').val()
                            };
                            firstCreditCardData = await this.tokenizeCardData(cardData);
                        }

                        if (!firstCreditCardData) {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }

                        if (this.isUsingSecondSavedCard()) {
                            secondCreditCarData = await this.getSavedCardDataByIndex(2);
                        } else if (this.isUsingNewSecondCard()) {
                            const cardData = {
                                cardHolderName: $(' #tuna_second_credit_card_holder').val(),
                                cardNumber: this.onlyNumbers($(' #tuna_second_credit_card_number').val()),
                                expirationMonth: parseInt($(' #tuna_second_credit_card_expiration_month').val()),
                                expirationYear: parseInt($(' #tuna_second_credit_card_expiration_year').val()),
                                cvv: $(' #tuna_second_credit_card_code').val()
                            };
                            secondCreditCarData = await this.tokenizeCardData(cardData);
                        }

                        if (!secondCreditCarData) {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }

                        firstCreditCardData.amount = parseFloat($(" #tuna_first_credit_card_value").val().replace(',', '.')).toFixed(2);
                        firstCreditCardData.installments = parseInt($(' #tuna_first_credit_card_installments option:selected').val());

                        secondCreditCarData.amount = parseFloat($(" #tuna_second_credit_card_value").val().replace(',', '.')).toFixed(2);
                        secondCreditCarData.installments = parseInt($(' #tuna_second_credit_card_installments option:selected').val());

                        self.endOrder(self, firstCreditCardData, secondCreditCarData, paymentData, messageContainer);
                    } else if (this.isUsingFirstSavedCard()) {
                        let creditCardData = await this.getSavedCardDataByIndex(1);

                        if (creditCardData) {
                            creditCardData.amount = getOrderTotal();
                            creditCardData.installments = parseInt($(' #tuna_credit_card_installments').val() || "1");
                            self.endOrder(self, creditCardData, null, paymentData, messageContainer);
                        } else {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }

                    } else {
                        let cardData = {
                            cardHolderName: $(' #tuna_credit_card_holder').val(),
                            cardNumber: this.onlyNumbers($(' #tuna_credit_card_number').val()),
                            expirationMonth: parseInt($(' #tuna_credit_card_expiration_month').val()),
                            expirationYear: parseInt($(' #tuna_credit_card_expiration_year').val()),
                            cvv: $(' #tuna_credit_card_code').val()
                        };

                        const creditCardData = await this.tokenizeCardData(cardData);

                        if (creditCardData) {
                            creditCardData.installments = parseInt($(' #tuna_credit_card_installments').val() || "1");
                            creditCardData.amount = getOrderTotal();
                            self.endOrder(self, creditCardData, null, paymentData, messageContainer);
                        } else {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }
                    }
                } else {
                    let validationLabels = [
                        "secondHolderInvalidInfo", "holderInvalidInfo", "cpfInvalidInfo", "cvvSavedInvalidInfo",
                        "secondCreditCardInvalidInfo", "creditCardInvalidInfo", "secondValidityDateInvalidInfo",
                        "validityDateInvalidInfo", "secondCvvInvalidInfo", "cvvInvalidInfo", "secondNoCreditCardSelected",
                        "noCreditCardSelected", "installmentsInvalidInfo"];

                    validationLabels.forEach(fieldID => {
                        $(" #" + fieldID).hide();
                    });

                    $(" #" + fieldCheckResponse).show();
                }
            },
        });
    }
);
