var moneyPattern = /\d{1,}/;
var $ = jQuery;

var currencyTypes = {
    'BRL': {
        language: 'pt-BR',
        symbol: 'R$',
        decimalDivisor: ',',
        decimalSeparator: '.',
        moneyPattern: moneyPattern,
        installmentPattern: /\(R\$.*\)/,
    },
    'USD': {
        language: 'en-US',
        symbol: '$',
        decimalDivisor: '.',
        decimalSeparator: ',',
        moneyPattern: moneyPattern,
        installmentPattern: /\(\$.*\)/,
    },
    'NZD': {
        language: 'en-US',
        symbol: 'NZ$',
        decimalDivisor: '.',
        decimalSeparator: ',',
        moneyPattern: moneyPattern,
        installmentPattern: /\(NZ\$.*\)/,
    },
};

function getSystemCurrency() {
    var defaultCurrency = 'USD';
    var currencySymbol = $(".sub .amount .price").html().replaceAll(',', '').replaceAll('.', '').replace(moneyPattern, '');
    for (var currency in currencyTypes) {
        if (currencyTypes[currency].symbol === currencySymbol) {
            return currency;
        }
    }

    return defaultCurrency;
}

function getFloatNumber(value, currency) {
    var currencyFormat = currencyTypes[currency];
    var floatNumber = value.replaceAll(currencyFormat.decimalSeparator, '').replace(currencyFormat.decimalDivisor, '.');

    return parseFloat(floatNumber, 10);
}

function formatCurrency(value, currency = 'BRL') {
    return new Intl.NumberFormat(currencyTypes[currency].language, { currency, minimumFractionDigits: 2 }).format(value);
}

function getOldOrderTotal(hasFees = false) {
    var orderSummary = $('.totals .amount .price');
    var systemCurrency = getSystemCurrency();
    var subTotal = 0;
    orderSummary.each(function (index, element) {
        if (index < (orderSummary.length - 1 - 1 * hasFees)) {
            var rawMoneyNumber = $(element).html().replace(currencyTypes[systemCurrency].symbol, '');
            subTotal += getFloatNumber(rawMoneyNumber, systemCurrency);
        }
    });

    return subTotal;
}

function extractValueFromInstallmentsOption(selectedInstallmentOption, currency) {
    var symbol = currencyTypes[currency].symbol;
    var pattern = currencyTypes[currency].installmentPattern;
    var decimalDivisor = currencyTypes[currency].decimalDivisor;
    var decimalSeparator = currencyTypes[currency].decimalSeparator;
    var newOrderTotal = selectedInstallmentOption.match(pattern)[0].substring(symbol.length + 1).trim().replaceAll(decimalSeparator, '');
    newOrderTotal = newOrderTotal.replace(decimalDivisor, '.').substring(0, newOrderTotal.length - 1);

    return getFloatNumber(newOrderTotal, currency);
}

function getNewOrderTotal(currency) {
    if ($('#payingWithTwoCards').val() == '0') {
        var instalmentTextDefault = $('#tuna_credit_card_installments option[value=1]').text();
        var installmentSelection = $('#tuna_credit_card_installments option:selected').text();
        var isValidInstallment = installmentSelection === 'Parcelas' || installmentSelection === '';
        var installmentText = (isValidInstallment) ? instalmentTextDefault : installmentSelection;
        return extractValueFromInstallmentsOption(installmentText, currency);
    } else {
        var firstCardInstallmentSelection = $('#tuna_first_credit_card_installments option:selected').text();
        var secondCardInstallmentSelection = $('#tuna_second_credit_card_installments option:selected').text();
        return extractValueFromInstallmentsOption(firstCardInstallmentSelection, currency) +
            extractValueFromInstallmentsOption(secondCardInstallmentSelection, currency);
    }
}

function refreshOrderInfo() {
    var newOrderTotal;
    var systemCurrency = getSystemCurrency();
    var hasFees = $('.tuna-order-fees').length > 0;
    var oldOrderTotal = getOldOrderTotal(hasFees); ''
    try {
        newOrderTotal = getNewOrderTotal(systemCurrency);
    } catch {
        newOrderTotal = oldOrderTotal;
    }
    insertOrderFeesHtml(oldOrderTotal, newOrderTotal);
    insertNewOrderTotalHtml(newOrderTotal);
}

function resetOrderInfo() {
    var hasFees = $('.tuna-order-fees').length > 0;
    var oldOrderTotal = getOldOrderTotal(hasFees);
    var newOrderTotal = oldOrderTotal;
    insertOrderFeesHtml(oldOrderTotal, newOrderTotal);
    insertNewOrderTotalHtml(newOrderTotal);
}

function insertOrderFeesHtml(oldOrderTotal, newOrderTotal) {
    var feeAmount = newOrderTotal - oldOrderTotal;
    var feesHtmlElement = $('.tuna-order-fees');
    var hasFees = feesHtmlElement.length > 0;
    if (hasFees) {
        feesHtmlElement.remove();
    }
    if (feeAmount === 0) return;

    var systemCurrency = getSystemCurrency();
    var systemSymbol = currencyTypes[systemCurrency].symbol;
    var feeAmountFormatted = formatCurrency(feeAmount, systemCurrency);

    var feeDescription = feeAmount > 0 ? 'Acréscimo de Juros' : 'Desconto';

    var feeOrderHtml = `<tr class="totals tuna-order-fees"> <th data-bind="i18n: title" class="mark" scope="row">${feeDescription}</th> <td class="amount">
<span class="price" data-bind="text: getValue(), attr: {'data-th': title}" data-th="Tuna Fees">${systemSymbol}${feeAmountFormatted}</span> </td> </tr>`;

    $('.grand').before(feeOrderHtml);
}

function insertNewOrderTotalHtml(newOrderTotal) {
    var systemCurrency = getSystemCurrency();
    var systemSymbol = currencyTypes[systemCurrency].symbol;
    var newOrderTotalFormatted = formatCurrency(newOrderTotal, systemCurrency);

    $(".grand .amount .price").html(`${systemSymbol}${newOrderTotalFormatted}`);
}