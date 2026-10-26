<?php

return [
    'advance_requires_residual' => 'An advance must leave a positive balance. Choose Final payment to settle the occurrence.',
    'final_total_mismatch' => 'The final total must equal previous payments plus this payment. For fixed amounts it must match the amount due.',
    'future_confirmation' => 'This occurrence is in the future. You can edit its forecast from the plan.',
    'automatic_confirmation_only' => 'Confirming an automatic recurrence only verifies its existing transaction. If it is missing, use Manage payment.',
    'updated' => 'Payment management updated.',
    'history_locked' => 'The adjustment history is permanent and cannot be modified.',
    'rectified_locked' => 'This transaction was adjusted. Check the payment history; it cannot be edited or deleted.',
    'request_reused' => 'This request was already used with different data. Reopen payment management.',
    'review_only' => 'A real duplicate charge or bank reversal requires checking the original transactions. Reporting it does not change balances.',
    'confirmation_required' => 'Explicitly confirm that the automatic debit did not occur before adjusting it.',
    'protected' => 'This payment has refunds, partial payments, reconciliations or other protected accounting links. No transaction was changed. Manage it through its history.',
    'no_payment' => 'There is no active transaction to confirm or correct. Link an existing payment or record one that is not already present.',
    'invalid' => 'The requested operation is unavailable.',
    'real_payment' => 'A manually verified payment or real transaction cannot be cancelled through this procedure. Check its history.',
    'account_change' => 'The transaction is already recorded on another account. Check the account in its history first; no duplicate will be created.',
    'new_payment_confirmation' => 'Confirm that the payment is not already among your transactions before recording a new one.',
];
