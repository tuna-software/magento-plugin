let tunaLibCredit;
let methodCredit = "CreditOnly";
if (window.checkoutConfig.payment.tunagateway.useSandboxBundle)
tunaLibCredit = "tuna_essential_sandbox";
else
tunaLibCredit = "tuna_essential";

define(
    [
        tunaLibCredit,
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

            const CpfCnpjMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length <= 11 ? '000.000.000-009' : '00.000.000/0000-00';
            };
            const cpfCnpjpOptions = {
                onKeyPress: function (val, e, field, options) {
                    field.mask(CpfCnpjMaskBehavior.apply({}, arguments), options);
                }
            };

            $('#div'+methodCredit + ' #tuna_credit_card_document').mask(CpfCnpjMaskBehavior, cpfCnpjpOptions);
            $("#div"+methodCredit + " #tuna_first_credit_card_value").mask("9999999999,99");
            $("#div"+methodCredit + " #tuna_second_credit_card_value").mask("999999999,99");
            $("#div"+methodCredit + " #tuna_credit_card_code").mask("999");
            $("#div"+methodCredit + " #tuna_second_credit_card_code").mask("999");
            $("#div"+methodCredit+ " CcCvv").mask("999");
            $("#div"+methodCredit + " #tuna_credit_card_number").mask("9999 9999 9999 9999");
            $("#div"+methodCredit + " #tuna_second_credit_card_number").mask("9999 9999 9999 9999");
            $("#div"+methodCredit + " #tuna_billing_address_zip").mask("99.999-999");

            $('#div'+methodCredit + ' #tuna_billing_address_phone').mask('(00) 0000-00009');

            $("#div"+methodCredit + " #tuna_billing_address_phone").live("blur", function (event) {
                if ($(this).val().length == 15) {
                    $('#div'+methodCredit + ' #tuna_billing_address_phone').mask('(00) 00000-0009');
                } else {
                    $('#div'+methodCredit + ' #tuna_billing_address_phone').mask('(00) 0000-00009');
                }
            });
            $(document).on('change', 'input[name$="[qty]"]', function () {
                var deferred = $.Deferred();
                getTotalsAction([], deferred);
            });
        });

        $('input[type=radio][name=billingAddress]').live("change", function () {
            $("#div"+methodCredit + " #billingAddressFields").hide();
        });

        function fillInstallmentsSelector() {
            const firstCreditCardValue = parseFloat($("#div"+methodCredit + " #tuna_first_credit_card_value").val().replace(',', '.')).toFixed(2);
            const secondCreditCardValue = parseFloat($("#div"+methodCredit + " #tuna_second_credit_card_value").val().replace(',', '.')).toFixed(2);

            $('#div'+methodCredit + ' #tuna_first_credit_card_installments').empty();
            $.each(getInstallments(firstCreditCardValue), function (i, item) {
                $('#div'+methodCredit + ' #tuna_first_credit_card_installments').append($('<option>', {
                    value: item.value,
                    text: item.text
                }));
            });
            $('#div'+methodCredit + ' #tuna_second_credit_card_installments').empty();
            $.each(getInstallments(secondCreditCardValue), function (i, item) {
                $('#div'+methodCredit + ' #tuna_second_credit_card_installments').append($('<option>', {
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
                changedFieldID = "#div"+methodCredit + " #tuna_first_credit_card_value";
                otherFieldID = "#div"+methodCredit + " #tuna_second_credit_card_value";
            } else {
                changedFieldID = "#div"+methodCredit + " #tuna_second_credit_card_value";
                otherFieldID = "#div"+methodCredit + " #tuna_first_credit_card_value";
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

        $('#div'+methodCredit + ' #tuna_first_credit_card_value').live("blur", _ => onPayWithTwoCardsValuesChanges(1));
        $('#div'+methodCredit + ' #tuna_second_credit_card_value').live("blur", _ => onPayWithTwoCardsValuesChanges(2));

        $('#div'+methodCredit + ' #tuna_first_credit_card_installments').live("change", _ => refreshOrderInfo());
        $('#div'+methodCredit + ' #tuna_second_credit_card_installments').live("change", _ => refreshOrderInfo());

        function secondCardRadioChanged() {
            if ($("#div"+methodCredit + " #tuna_second_card_radio_saved").prop("checked")) {
                $("#div"+methodCredit + " #secondNewCardDiv").hide();
                $("#div"+methodCredit + " #secondSavedCardDiv").show();
            } else {
                $("#div"+methodCredit + " #secondSavedCardDiv").hide();
                $("#div"+methodCredit + " #secondNewCardDiv").show();
            }
        };

        function cardRadioChanged() {
            if ($("#div"+methodCredit + " #tuna_card_radio_saved").prop("checked")) {
                DisableAllMethods();
                $("#div"+methodCredit + " #creditCardPaymentDiv").show();
                $("#div"+methodCredit + " #lblHolderNameCard").show();
                $("#div"+methodCredit + " #savedCardDiv").show();            
                $('#div'+methodCredit + ' #tuna_credit_card_document').show();
                $('#div'+methodCredit + ' #cpfCnpjDiv').show();
                $('#div'+methodCredit + ' #tuna_credit_card_installments').prop('selectedIndex', 1);
                refreshOrderInfo();
            } else if ($("#div"+methodCredit + " #tuna_card_radio_new").prop("checked")) {
                DisableAllMethods();
                $("#div"+methodCredit + " #creditCardPaymentDiv").show();                
                $("#div"+methodCredit + " #lblHolderNameCard").show();
                $("#div"+methodCredit + " #newCardDiv").show();                
                $('#div'+methodCredit + ' #tuna_credit_card_document').show();
                $('#div'+methodCredit + ' #cpfCnpjDiv').show();
                $('#div'+methodCredit + ' #tuna_credit_card_installments').prop('selectedIndex', 1);
                refreshOrderInfo();
            } else if ($("#div"+methodCredit + " #tuna_crypto_radio").prop("checked")) {
                DisableAllMethods();
                $("#div"+methodCredit + " #cryptoDiv").show(); 
                resetOrderInfo();
            } else if ($("#div"+methodCredit + " #tuna_pix_radio").prop("checked")) {
                DisableAllMethods();
                $("#div"+methodCredit + " #pixDiv").show(); 
                resetOrderInfo();
            } else if ($("#div"+methodCredit + " #tuna_link_radio").prop("checked")) {
                DisableAllMethods();
                $("#div"+methodCredit + " #linkDiv").show(); 
                resetOrderInfo();
            } else {
                DisableAllMethods();                
                $('#div'+methodCredit + ' #cpfCnpjDiv').show();
                $("#div"+methodCredit + " #lblHolderNameBoleto").show();
                $('#div'+methodCredit + ' #tuna_credit_card_document').show();                
                $("#div"+methodCredit + " #boletoDiv").show();
                $("#div"+methodCredit+ " checkout").html("Gerar boleto");
                resetOrderInfo();
            }
        };

        function payUsingTwoCardsClicked() {
            if ($("#div"+methodCredit + " #payingWithTwoCards").val() === "0") {
                // Pay with two cards
                const savedCards = window.checkoutConfig.payment.tunagateway.savedCreditCards;
                $("#div"+methodCredit + " #secondCardSelectorDiv").show();
                if (!savedCards || savedCards.length == 0) {
                    $("#div"+methodCredit + " #tuna_second_savedCard_label").remove();
                    $("#div"+methodCredit + " #tuna_second_new_card_radio").prop("checked", true);
                    $("#div"+methodCredit + " #secondNewCardDiv").show();
                } else {
                    $("#div"+methodCredit + " #tuna_second_card_radio_saved").prop("checked", true);
                    $("#div"+methodCredit + " #secondSavedCardDiv").show();
                }
                $("#div"+methodCredit + " #payingWithTwoCards").val("1");
                $("#div"+methodCredit + " #payUsingTwoCardsLink").text("Pagar usando um cartão");
                $("#div"+methodCredit + " #installmentsDiv").hide();

                const valuePerCard = (getOrderTotal() / 2).toFixed(2);
                $("#div"+methodCredit + " #tuna_second_credit_card_value").val(valuePerCard.toString().replace('.', ','));
                $("#div"+methodCredit + " #firstCardValueDiv").show();
                $("#div"+methodCredit + " #first_card_installments_div").show();
                $("#div"+methodCredit + " #tuna_first_credit_card_value").val((valuePerCard * 2 == getOrderTotal() ? valuePerCard : valuePerCard + 0.01).toString().replace('.', ','));

                fillInstallmentsSelector();
            } else {
                // Pay with one card
                $("#div"+methodCredit + " #payingWithTwoCards").val("0");
                $("#div"+methodCredit + " #installmentsDiv").show();
                $("#div"+methodCredit + " #first_card_installments_div").hide();
                $("#div"+methodCredit + " #secondCardSelectorDiv").hide();
                $("#div"+methodCredit + " #firstCardValueDiv").hide();
                $("#div"+methodCredit + " #payUsingTwoCardsLink").text("Pagar usando dois cartões");
                resetOrderInfo();
            }
        };

        $("#div"+methodCredit + " #tuna_card_radio_new").live("change", cardRadioChanged);
        $("#div"+methodCredit + " #tuna_card_radio_saved").live("change", cardRadioChanged);
        $("#div"+methodCredit + " #tuna_boleto_radio").live("change", cardRadioChanged);
        $("#div"+methodCredit + " #tuna_crypto_radio").live("change", cardRadioChanged);
        $("#div"+methodCredit + " #tuna_pix_radio").live("change", cardRadioChanged);
        $("#div"+methodCredit + " #tuna_link_radio").live("change", cardRadioChanged);
        $("#div"+methodCredit + " #tuna_second_card_radio_saved").live("change", secondCardRadioChanged);
        $("#div"+methodCredit + " #tuna_second_new_card_radio").live("change", secondCardRadioChanged);
        $("#div"+methodCredit + " #payUsingTwoCardsLink").live("click", payUsingTwoCardsClicked);
        $('#div'+methodCredit + ' #tuna').live("change", () => {
            $("#div"+methodCredit + " #tuna_credit_card_installments").prop('selectedIndex', 1);
            refreshOrderInfo();
        });
        $("#div"+methodCredit + " #checkmo").live("click", resetOrderInfo);
        $("#div"+methodCredit + " #tuna_credit_card_installments").live("change", () => {
            if ($("#div"+methodCredit + " #tuna").prop("checked")) {
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
        function DisableAllMethods(){ 
            $("#div"+methodCredit + " #savedCardDiv").hide();
            $("#div"+methodCredit + " #creditCardPaymentDiv").hide();
            $("#div"+methodCredit + " #lblHolderNameBoleto").hide();
            $("#div"+methodCredit + " #lblHolderNameCard").hide();
            $('#div'+methodCredit + ' #tuna_credit_card_document').hide();
            $('#div'+methodCredit + ' #cpfCnpjDiv').hide();
            $("#div"+methodCredit + " #newCardDiv").hide();
            $("#div"+methodCredit + " #cryptoDiv").hide();                        
            $("#div"+methodCredit + " #boletoDiv").hide();
            $("#div"+methodCredit + " #pixDiv").hide();
            $("#div"+methodCredit + " #linkDiv").hide();
            $("#div"+methodCredit+ " checkout").html("Pagar");
        }
        function getOrderTotal() {
            return parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10);
        }

        $("#div"+methodCredit + " #tuna_billing_address_country").live("change", _ => {
            // Fix unknown info
            if ($("#div"+methodCredit + " #control").length == 1) {
                $("#div"+methodCredit + " #control").remove();
                $("#div"+methodCredit + " #tuna_billing_address_country").val("BR");
            }

            let selectCountryID = $("#div"+methodCredit + " #tuna_billing_address_country option:selected").val();
            let countryRegions = window.checkoutConfig.countries.find(c => c.id == selectCountryID).regions;
            $('#div'+methodCredit + ' #tuna_billing_address_state').find('option').remove();
            countryRegions.forEach(region => {
                let option = new Option(region.name, region.id);
                $('#div'+methodCredit + ' #tuna_billing_address_state').append(option);
            });
        });

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/credit',
            },
            afterRender: function () {
                $('#div'+methodCredit + ' #tuna').prop('checked', true);
                $("#div"+methodCredit + " #lblTunaPaymentTitle").html(window.checkoutConfig.payment.tunagateway.title_credit);
                if (!window.checkoutConfig.payment.tunagateway.sessionid) {
                    if (!this.allowBoleto()) {
                        $("#div"+methodCredit + " #tuna_payment_method_content").remove();
                        $("#div"+methodCredit + " #badNewsDiv").show();
                        return;
                    } else {
                        $("#div"+methodCredit + " #creditCardPaymentDiv").remove();
                        $("#div"+methodCredit + " #tuna_savedCard_label").remove();
                        $("#div"+methodCredit + " #tuna_newCard_label").remove();
                        $("#div"+methodCredit + " #cryptoDiv").remove();
                        $("#div"+methodCredit + " #pixDiv").remove();
                        $("#div"+methodCredit + " #linkDiv").remove();
                        $("#div"+methodCredit + " #tuna_boleto_radio").prop("checked", true);
                        $("#div"+methodCredit + " #boletoDiv").show();
                        return;
                    }
                }

                if (!this.allowBoleto()) {
                    $("#div"+methodCredit + " #tuna_boleto_label").remove();
                    $("#div"+methodCredit + " #boletoDiv").remove();
                }
                if (!this.allowCrypto()) {
                    $("#div"+methodCredit + " #tuna_crypto_label").remove();
                    $("#div"+methodCredit + " #cryptoDiv").remove();
                }
                if (!this.allowPix()) {
                    $("#div"+methodCredit + " #tuna_pix_label").remove();
                    $("#div"+methodCredit + " #pixDiv").remove();
                }
                if (!this.allowLink()) {
                    $("#div"+methodCredit + " #tuna_link_label").remove();
                    $("#div"+methodCredit + " #linkDiv").remove();
                }
                if (!this.allowCard()) {
                    $("#div"+methodCredit + " #creditCardPaymentDiv").remove();
                    $("#div"+methodCredit + " #tuna_savedCard_label").remove();
                    $("#div"+methodCredit + " #tuna_newCard_label").remove();
                    if (this.allowPix()) {
                        this.disableAll();
                        $("#div"+methodCredit + " #tuna_pix_radio").prop("checked", true);
                        $("#div"+methodCredit + " #pixDiv").show();            
                       
                    }else
                    {
                        if (this.allowBoleto()) {
                            this.disableAll();
                            $("#div"+methodCredit + " #tuna_boleto_radio").prop("checked", true);
                            $("#div"+methodCredit + " #boletoDiv").show();                            
                        }else
                        {
                            if (this.allowCrypto()) {
                                this.disableAll();
                                $("#div"+methodCredit + " #tuna_cripto_radio").prop("checked", true);
                                $("#div"+methodCredit + " #cryptoDiv").show(); 
                            }else
                            {if (this.allowLink()) {
                                this.disableAll();
                                $("#div"+methodCredit + " #tuna_link_radio").prop("checked", true);
                                $("#div"+methodCredit + " #linkDiv").show();            
                               
                            }else
                            {
                                this.disableAll();    
                                $("#div"+methodCredit+ " checkout").hide();                            
                            }
                            }
                        }
                    }
                }
                if (!this.allowPaymentWithTwoCards()) {
                    $("#div"+methodCredit + " #payUsingTwoCardsLink").remove();
                }
                if (this.getStoredCreditCards() && this.getStoredCreditCards().length > 0) {
                    $("#div"+methodCredit + " #tuna_card_radio_saved").prop("checked", true);
                    $("#div"+methodCredit + " #savedCardDiv").show();
                } else {
                    $("#div"+methodCredit + " #tuna_savedCard_label").remove();
                    $("#div"+methodCredit + " #tuna_card_radio_new").prop("checked", true);
                    $("#div"+methodCredit + " #newCardDiv").show();
                }
            },
            enableBillingAddressFields: function () {
                $("#div"+methodCredit + " #billingAddressFields").show();
                $("input[name='billingAddress']").prop("checked", false);
            },
            disableAll:function(){
                DisableAllMethods();
            },
            hideBillingAddressFields: function () {
                $('#div'+methodCredit + ' #billingAddressFields').hide();
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
            allowLink: function () {
                return window.checkoutConfig.payment.tunagateway.allow_link &&
                    window.checkoutConfig.payment.tunagateway.allow_link === "1";
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
                return (window.checkoutConfig.payment.tunagateway.allow_card &&
                window.checkoutConfig.payment.tunagateway.allow_card === "1") && !(window.checkoutConfig.payment.tunagateway.tuna_active &&
                    window.checkoutConfig.payment.tunagateway.tuna_active === "1");
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
                    $("#div"+methodCredit + " #tuna_card_cvv_" + cc.token).show();
                }
                else {
                    $("input[id^='tuna_second_card_cvv_']").hide();
                    $("#div"+methodCredit + " #tuna_second_card_cvv_" + cc.token).show();
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
                    'buyer_document': $('#div'+methodCredit + ' #tuna_credit_card_document').val(),
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_data': JSON.stringify(creditCards),
                    'buyer_name': isBoleto ? $('#div'+methodCredit + ' #tuna_boleto_holder').val() : "",
                    'is_boleto_payment': isBoleto ? "true" : "false",
                    'is_crypto_payment': isCrypto ? "true" : "false",
                    'is_pix_payment': isPix ? "true" : "false",
                    'is_link_payment': isLink ? "true" : "false",
                    'payment_type': isBoleto ? "TUNA_EBOL" : isPix ? "TUNA_EPIX" : isCrypto ? "TUNA_ECRYPTO" : isLink ? "TUNA_ELINK" : "TUNA_ECAC",
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
                return $("#div"+methodCredit + " #tuna_card_radio_saved").prop("checked");
            },
            isUsingSecondSavedCard: function () {
                return $("#div"+methodCredit + " #tuna_second_card_radio_saved").prop("checked");
            },
            isUsingNewFirstCard: function () {
                return $("#div"+methodCredit + " #tuna_card_radio_new").prop("checked");
            },
            isUsingNewSecondCard: function () {
                return $("#div"+methodCredit + " #tuna_second_new_card_radio").prop("checked");
            },
            isUsingTwoCards: function () {
                return $("#div"+methodCredit + " #payingWithTwoCards").val() === "1";
            },
            isBoletoPayment: function () {
                return $("#div"+methodCredit + " #tuna_boleto_radio").prop("checked");
            },
            isCryptoPayment: function () {
                return $("#div"+methodCredit + " #tuna_crypto_radio").prop("checked");
            },
            isPixPayment: function () {
                return $("#div"+methodCredit + " #tuna_pix_radio").prop("checked");
            },
            isLinkPayment: function () {
                return $("#div"+methodCredit + " #tuna_link_radio").prop("checked");
            },
            getSelectedCardToken: function (cardIndex) {
                const fieldname = cardIndex === 1 ? "firstStoredCard" : "secondStoredCard";
                return $(`input[name='${fieldname}']:checked`).attr("id").substring(cardIndex === 1 ? 16 : 17, $(`input[name='${fieldname}']:checked`).attr("id").length);
            },
            validateFirstNewCardData: function () {
                if (!$('#div'+methodCredit + ' #tuna_credit_card_holder')[0].value)
                    return "holderInvalidInfo";

                let cardNumber = this.onlyNumbers($('#div'+methodCredit + ' #tuna_credit_card_number')[0].value);
                if (!cardNumber || !valid_credit_card(cardNumber))
                    return "creditCardInvalidInfo";

                let expirationYear = $('#div'+methodCredit + ' #tuna_credit_card_expiration_year')[0].value;
                let expirationMonth = $('#div'+methodCredit + ' #tuna_credit_card_expiration_month')[0].value;

                if (!expirationMonth || !expirationYear)
                    return "validityDateInvalidInfo";

                let expireDate = new Date(expirationYear, parseInt(expirationMonth) - 1);
                if (expireDate < new Date())
                    return "validityDateInvalidInfo";

                if (!$("#div"+methodCredit + " #tuna_credit_card_code")[0].value || $("#div"+methodCredit + " #tuna_credit_card_code")[0].value < 3)
                    return "cvvInvalidInfo";
            },
            validateSecondNewCardData: function () {
                if (!$('#div'+methodCredit + ' #tuna_second_credit_card_holder')[0].value)
                    return "secondHolderInvalidInfo";

                let cardNumber = this.onlyNumbers($('#div'+methodCredit + ' #tuna_second_credit_card_number')[0].value);
                if (!cardNumber || !valid_credit_card(cardNumber))
                    return "secondCreditCardInvalidInfo";

                let expirationYear = $('#div'+methodCredit + ' #tuna_second_credit_card_expiration_year')[0].value;
                let expirationMonth = $('#div'+methodCredit + ' #tuna_second_credit_card_expiration_month')[0].value;

                if (!expirationMonth || !expirationYear)
                    return "secondValidityDateInvalidInfo";

                let expireDate = new Date(expirationYear, parseInt(expirationMonth) - 1);
                if (expireDate < new Date())
                    return "secondValidityDateInvalidInfo";

                if (!$("#div"+methodCredit + " #tuna_second_credit_card_code")[0].value || $("#div"+methodCredit + " #tuna_second_credit_card_code")[0].value < 3)
                    return "secondCvvInvalidInfo";
            },
            validateFirstSavedCardData: function () {
                if ($("input[name='firstStoredCard']:checked").length > 0) {
                    if (!$("#div"+methodCredit + " #tuna_card_cvv_" + this.getSelectedCardToken(1)).val())
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
                } else if (this.isBoletoPayment()) {
                    if (!$('#div'+methodCredit + ' #tuna_boleto_holder')[0].value)
                        return "boletoHolderInvalidInfo";
                } else if (this.isCryptoPayment()) {
                } else if (this.isPixPayment()) {
                } else if (this.isLinkPayment()) {
                } else {
                    return "error";
                }

                let document = $('#div'+methodCredit + ' #tuna_credit_card_document')[0].value;
                const hasDocument = !!document;
                const hasDocumentCheck = !this.isPixPayment() && !this.isCryptoPayment() && !this.isLinkPayment();
                const isValidDocument = this.isCNPJValid(document) || this.isCPFValid(document);
                if (hasDocumentCheck && !(hasDocument && isValidDocument)) {
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
                if ($("#div"+methodCredit + " #customer-email").val() == "" && $("#div"+methodCredit+ " authentication-wrapper").css("display") == "block") {
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
                                CardHolderName: $('#div'+methodCredit + ' #tuna_credit_card_holder').val(),
                                CardNumber: this.onlyNumbers($('#div'+methodCredit + ' #tuna_credit_card_number').val()),
                                ExpirationMonth: parseInt($('#div'+methodCredit + ' #tuna_credit_card_expiration_month').val()),
                                ExpirationYear: parseInt($('#div'+methodCredit + ' #tuna_credit_card_expiration_year').val()),
                                Cvv: $('#div'+methodCredit + ' #tuna_credit_card_code').val()
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
                                CardHolderName: $('#div'+methodCredit + ' #tuna_second_credit_card_holder').val(),
                                CardNumber: this.onlyNumbers($('#div'+methodCredit + ' #tuna_second_credit_card_number').val()),
                                ExpirationMonth: parseInt($('#div'+methodCredit + ' #tuna_second_credit_card_expiration_month').val()),
                                ExpirationYear: parseInt($('#div'+methodCredit + ' #tuna_second_credit_card_expiration_year').val()),
                                Cvv: $('#div'+methodCredit + ' #tuna_second_credit_card_code').val()
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

                        firstCreditCardData.amount = parseFloat($("#div"+methodCredit + " #tuna_first_credit_card_value").val().replace(',', '.')).toFixed(2);
                        firstCreditCardData.installments = parseInt($('#div'+methodCredit + ' #tuna_first_credit_card_installments option:selected').val());

                        secondCreditCarData.amount = parseFloat($("#div"+methodCredit + " #tuna_second_credit_card_value").val().replace(',', '.')).toFixed(2);
                        secondCreditCarData.installments = parseInt($('#div'+methodCredit + ' #tuna_second_credit_card_installments option:selected').val());

                        self.endOrder(self, firstCreditCardData, secondCreditCarData, paymentData, messageContainer);
                    }
                    else if (this.isUsingFirstSavedCard()) {
                        let creditCardData = await this.getSavedCardDataByIndex(1);

                        if (creditCardData) {
                            creditCardData.amount = getOrderTotal();
                            creditCardData.installments = parseInt($('#div'+methodCredit + ' #tuna_credit_card_installments').val() || "1");
                            self.endOrder(self, creditCardData, null, paymentData, messageContainer);
                        }
                        else {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }

                    } else if (this.isBoletoPayment()) {
                        self.endOrder(self, null, null, paymentData, messageContainer, true, false, false, false);
                    } else if (this.isCryptoPayment()) {
                        self.endOrder(self, null, null, paymentData, messageContainer, false, false, true, false);
                    } else if (this.isPixPayment()) {
                        self.endOrder(self, null, null, paymentData, messageContainer, false, true, false, false);
                    } else if (this.isLinkPayment()) {
                        self.endOrder(self, null, null, paymentData, messageContainer, false, false, false, true);
                    } else {
                        let cardData = {
                            CardHolderName: $('#div'+methodCredit + ' #tuna_credit_card_holder').val(),
                            CardNumber: this.onlyNumbers($('#div'+methodCredit + ' #tuna_credit_card_number').val()),
                            ExpirationMonth: parseInt($('#div'+methodCredit + ' #tuna_credit_card_expiration_month').val()),
                            ExpirationYear: parseInt($('#div'+methodCredit + ' #tuna_credit_card_expiration_year').val()),
                            Cvv: $('#div'+methodCredit + ' #tuna_credit_card_code').val()
                        };

                        const creditCardData = await this.tokenizeCardData(cardData);

                        if (creditCardData) {
                            creditCardData.installments = parseInt($('#div'+methodCredit + ' #tuna_credit_card_installments').val() || "1");
                            creditCardData.amount = getOrderTotal();
                            self.endOrder(self, creditCardData, null, paymentData, messageContainer);
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
                    let validationLabels = [
                        "secondHolderInvalidInfo", "holderInvalidInfo", "cpfInvalidInfo", "cvvSavedInvalidInfo",
                        "boletoHolderInvalidInfo", "secondCreditCardInvalidInfo", "creditCardInvalidInfo", "secondValidityDateInvalidInfo",
                        "validityDateInvalidInfo", "secondCvvInvalidInfo", "cvvInvalidInfo", "secondNoCreditCardSelected",
                        "noCreditCardSelected", "installmentsInvalidInfo"];

                    validationLabels.forEach(fieldID => {
                        $("#div"+methodCredit + " #" + fieldID).hide();
                    });

                    $("#div"+methodCredit + " #" + fieldCheckResponse).show();
                }
            }
        });
    }
);
