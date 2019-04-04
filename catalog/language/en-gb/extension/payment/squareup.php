<?php
// Text
$_['text_accepted_cards']                       = 'Accepted Cards:';
$_['text_card_cvc']                             = 'Card Security Code (CVC):';
$_['text_card_details']                         = 'Pay with Credit/Debit Card';
$_['text_card_ends_in']                         = 'Pay with existing %s card that ends on XXXX XXXX XXXX %s';
$_['text_card_expiry']                          = 'Card Expiry (MM/YY):';
$_['text_card_number']                          = 'Card Number:';
$_['text_card_placeholder']                     = 'XXXX XXXX XXXX XXXX';
$_['text_card_save']                            = 'Save card for future use?';
$_['text_card_zip']                             = 'Card Zip Code:';
$_['text_card_zip_placeholder']                 = 'Zip Code';
$_['text_cron_expiration_message_expired']      = 'The following non-captured transactions have expired. They have been automatically voided by Square due to 6 days of inactivity. Customers have been notified.';
$_['text_cron_expiration_message_expiring']     = 'The following non-captured transactions are about to expire. Please take action as soon as possible.';
$_['text_cron_expiration_subject']              = 'Square non-captured transactions';
$_['text_cron_fail_charge']                     = 'Profile <strong>#%s</strong> could not get charged with <strong>%s</strong>';
$_['text_cron_inventory_dashboard']             = '<a href="%s" target="_blank">Click here</a> to access your Square Dashboard to manually set your inventories.<br /><br />For more detailed instructions, here\'s a helpful <a href="%s" target="_blank">Video Tutorial</a>!';
$_['text_cron_inventory_links_intro']           = 'New items have just been synced. Some of them have been added to Square with empty inventories.';
$_['text_cron_inventory_links_more']            = '... %s other item(s).';
$_['text_cron_message']                         = 'Here is a list of all CRON tasks performed by your Square extension:';
$_['text_cron_subject']                         = 'Square CRON job summary';
$_['text_cron_success_charge']                  = 'Profile <strong>#%s</strong> was charged with <strong>%s</strong>';
$_['text_cron_summary_error_heading']           = 'Transaction Errors:';
$_['text_cron_summary_fail_heading']            = 'Failed Transactions (Profiles Suspended):';
$_['text_cron_summary_fail_sync']               = 'Sync failed. Errors:';
$_['text_cron_summary_success_heading']         = 'Successful Transactions:';
$_['text_cron_summary_success_sync_heading']    = 'Sync between Square and OpenCart:';
$_['text_cron_summary_token_heading']           = 'Refresh of access token:';
$_['text_cron_summary_token_updated']           = 'Access token updated successfully!';
$_['text_cron_tax_rates_intro']                 = 'The last Square sync has resulted in <strong>%s</strong> new Tax Rate(s) in your OpenCart store.<br /><br />Please visit <a href="%s" target="_blank">the admin panel of the Square Payment Extension</a> to configure the appropriate Geo Zone for each new Tax Rate:';
$_['text_cron_tax_rates_subject']               = 'Square - newly created Tax Rates';
$_['text_cron_warnings_intro']                  = 'The last Square sync has resulted in <strong>%s</strong> issues:';
$_['text_cron_warnings_subject']                = 'Square Catalog Sync: A few items to update manually';
$_['text_cvv']                                  = 'CVV';
$_['text_default_squareup_name']                = 'Credit / Debit Card';
$_['text_expiry']                               = 'MM/YY';
$_['text_length']                               = ' for %s payments';
$_['text_loading']                              = 'Loading... Please wait...';
$_['text_new_card']                             = '+ Add new card';
$_['text_order_error_mail_intro']               = 'The following order <strong>#%s</strong> was SUCCESSFULLY charged; however it was submitted to Square as a non-Itemized "Custom Amount" transaction due to the following error:';
$_['text_order_error_mail_outro']               = 'Because of the order being recorded as "Custom Amount", you may need to manually adjust your accounting and inventory entries in Square to reflect the itemization of the order.<br /><br />To prevent this issue in the future:<br /><br />#1 - Please first ensure you have the latest version of the Square plug-in.<br />#2 - If upgrading does not resolve the issue, please file a support ticket here [ <a href="%s" target="_blank">%s</a> ] with as much information as possible.<br /><br />This is an automated email. Please do not reply.';
$_['text_order_error_mail_subject']             = 'Square Order Issue';
$_['text_order_id']                             = 'Order #%s';
$_['text_pay_with_applepay']                    = 'Pay with Apple Pay';
$_['text_pay_with_wallet']                      = 'Pay with a Digital Wallet';
$_['text_recurring']                            = '%s every %s %s';
$_['text_saved_card']                           = 'Use Saved Card:';
$_['text_secured']                              = 'Secured by Square';
$_['text_squareup_profile_suspended']           = ' Your recurring payments have been suspended. Please contact us for more details.';
$_['text_squareup_recurring_expired']           = ' Your recurring payments have expired. This was your last payment.';
$_['text_squareup_trial_expired']               = ' Your trial period has expired.';
$_['text_sync_disabled']                        = 'Sync is disabled. No sync has been performed.';
$_['text_token_expired_message']                = "The Square payment extension's access token connecting it to your Square account has expired. You need to verify your application credentials and CRON job in the extension settings and connect again.";
$_['text_token_expired_subject']                = 'Your Square access token has expired!';
$_['text_token_issue_customer_error']           = 'We are experiencing a technical outage in our payment system. Please try again later.';
$_['text_token_revoked_message']                = "The Square payment extension's access to your Square account has been revoked through the Square Dashboard. You need to verify your application credentials in the extension settings and connect again.";
$_['text_token_revoked_subject']                = 'Your Square access token has been revoked!';
$_['text_trial']                                = '%s every %s %s for %s payments then ';
$_['text_view']                                 = 'VIEW';
$_['text_wallet_details']                       = 'Pay with a Digital Wallet';

// Error
$_['error_browser_not_supported']               = 'Error: The payment system no longer supports your web browser. Please update or use a different one.';
$_['error_card_invalid']                        = 'Error: Card is invalid!';
$_['error_currency_invalid']                    = 'The expected currency is not supported on this store.';
$_['error_generic']                             = 'Unexpected website error. Please contact the store owner on <strong>%s</strong> or e-mail <strong>%s</strong>. Note that your transaction may be processed.';
$_['error_price_invalid_negative']              = 'The recurring price is negative. This amount cannot be charged by Square.';
$_['error_squareup_cron_token']                 = 'Error: Access token could not get refreshed. Please connect your Square Payment extension via the OpenCart admin panel.';
$_['error_currency_mismatch']                   = 'Your default store currency is different than your Square location currency, therefore the catalog was not synced. In order for Catalog Sync to work, your default OpenCart currency must be %s.';
// Warning
$_['warning_currency_converted']                = 'Warning: The total paid amount will be converted into <strong>%s</strong> at a conversion rate of <strong>%s</strong>. The expected transaction amount will be <strong>%s</strong>.';

// Statuses
$_['squareup_status_comment_authorized']        = 'The card transaction has been authorized but not yet captured.';
$_['squareup_status_comment_captured']          = 'The card transaction was authorized and subsequently captured (i.e., completed).';
$_['squareup_status_comment_failed']            = 'The card transaction failed.';
$_['squareup_status_comment_voided']            = 'The card transaction was authorized and subsequently voided (i.e., canceled).   ';

// Override errors
$_['squareup_error_field']                                  = ' Field: %s';
$_['squareup_override_error_billing_address.country']       = 'Payment Address country is not valid. Please modify it and try again.';
$_['squareup_override_error_email_address']                 = 'Your customer e-mail address is not valid. Please modify it and try again.';
$_['squareup_override_error_phone_number']                  = 'Your customer phone number is not valid. Please modify it and try again.';
$_['squareup_override_error_shipping_address.country']      = 'Shipping Address country is not valid. Please modify it and try again.';
