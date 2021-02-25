<?php
// Heading
//Check currency and show accordingly.
$_['heading_title'] = 'Genoapay';

// Tabs
$_['tab_general'] = 'General';
$_['tab_order_statuses'] = 'Order Statuses';
$_['tab_order_log'] = 'Order Logs';

// Text
$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified the payment configuration!';
$_['text_edit_genoapay'] = 'Edit Genoapay Payment';
$_['text_genoapay'] = '<a target="_BLANK" href="https://www.genoapay.com/"><img src="view/image/payment/genoapay_small.svg" alt="Genoapay Payment" title="Genoapay Payment" /></a>';
$_['text_authorization'] = 'Authorization';
$_['text_sale'] = 'Sale';
$_['text_production'] = 'Production';
$_['text_sandbox'] = 'Sandbox';
$_['text_check_configuration'] = 'Check the current configuration';
$_['text_gateway_configuration_warning'] = 'Your API credentials are invalid, please check again';

// Entry
$_['entry_title'] = 'Title';
$_['entry_email'] = 'E-Mail';
$_['entry_environment'] = 'Environment';
$_['entry_debug'] = 'Debug Mode';
$_['entry_order_total'] = 'Minimum Order Amount';
$_['entry_production_api_key'] = 'Production API Key';
$_['entry_production_api_secret'] = 'Production API Secret';
$_['entry_sandbox_api_key'] = 'Sandbox API Key';
$_['entry_sandbox_api_secret'] = 'Sandbox API Secret';
$_['entry_sort_order'] = 'Sort order';
$_['entry_status'] = 'Status';
$_['entry_description'] = 'Description';
$_['entry_order_completed_status'] = 'Completed Order Status';
$_['entry_order_pending_status'] = 'Pending Order Status';
$_['entry_order_failed_status'] = 'Failed/Cancelled Order Status';
$_['entry_order_refunded_status'] = 'Refunded Order Status';
$_['entry_order_partial_refunded_status'] = 'Partial Refunded Order Status';

// Help
$_['help_test'] = 'Use the live or testing (sandbox) gateway server to process transactions?';
$_['help_debug'] = 'Turn on the debug mode to record every request and response';
$_['help_total'] = 'The checkout total the order must reach before this payment method becomes active';
$_['help_environment'] = 'Sandbox is for testing purpose only.';
$_['help_title'] = 'This controls the title which the user sees during checkout.';
$_['help_description'] = 'This option can be set from your account portal. When the Save Changes button is clicked, this option will update automatically.';
$_['entry_sort_order'] = 'Sort order';
$_['help_production_api_key'] = 'The Public Key for your GenoaPay account.';
$_['help_production_api_secret'] = 'The Private Key for your GenoaPay account.';
$_['help_sandbox_api_key'] = 'The Public Key for your sandbox account.';
$_['help_sandbox_api_secret'] = 'The Private Key for your sandbox account.';
$_['help_order_total'] = 'This option can be set from your account portal. When the Save Changes button is clicked, this option will update automatically.';

// Error
$_['error_invalid_configuration'] = 'This method is not available with your current configuration! It should be in New Zealand with NZD as the currency.';
$_['error_permission'] = 'Warning: You do not have permission to modify payment configuration!';
$_['error_email'] = 'E-Mail required!';
$_['genoapay_transaction_not_found'] = 'The payment transaction with given token is not exist!';
$_['genoapay_transaction_payment_exceed'] = 'The refund amount is bigger than the available value!';
$_['genoapay_environment_required'] = 'You have to define the payment environment!';
$_['genoapay_invalid_environment'] = 'The environment name is invalid!';
$_['genoapay_api_credentials_required'] = 'Client ID or Secret cannot be blank!';

// Message
$_['genoapay_refund_order_message'] = 'Your order has been refunded successfully!';
$_['genoapay_refund_order_history_message'] = 'New refund transaction added, refund transaction ID: %s, amount: %s!';

// Order Statuses
$_['completed_order_status'] = 'Complete';
$_['pending_order_status'] = 'Pending';
$_['cancelled_order_status'] = 'Cancelled';
$_['partial_refunded_order_status'] = 'Partial Refunded';
$_['refunded_order_status'] = 'Refunded';

//Script
$_['genoapay_refund_button'] = '<div style="display: inline;"><a id="genoapay-refund-button" class="btn btn-danger btn-xs" style="float: right;padding: 3px 2px;" href="javascript:void(0)" data-href="{{{refund_url}}}">Refund</a><input id="genoapay-refund-amount" type="number" style="max-width: 100px; color: black; float: right; padding: 2px; margin-right: 5px;" value="{{{refund_amount}}}" data-max-amount="{{{refund_amount}}}"></div>';
$_['genoapay_refund_script'] = "<script>\n;(function(){\nvar paymentCell = document.querySelector(\"button[title='{{{text_payment_method}}}']\"); if (paymentCell) {\n var secondCell=paymentCell.parentElement.parentElement.children[1]; \n secondCell=secondCell.innerHTML=secondCell.innerHTML + '{{{refund_button}}}'; \n}})();\n</script>";
