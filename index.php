<?php
//##copyright##

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$error = false;
	$tplFile = 'error';

	$status = isset($iaCore->requestPath[0]) ? iaSanitize::paranoid($iaCore->requestPath[0]) : 'result';

	switch ($status)
	{
		case 'success':

			if (isset($_POST) && !empty($_POST))
			{
				// current date
				$tm = getdate(time() + 9 * 3600);
				$date = "$tm[year]-$tm[mon]-$tm[mday] $tm[hours]:$tm[minutes]:$tm[seconds]";

				// validate hash
				$hashArray = array();
				foreach (array('OutSum', 'InvId', 'shp_member', 'shp_salt') as $key)
				{
					if (isset($_POST[$key]))
					{
						$hashValue = ('shp_' == substr($key, 0, 4)) ? $key . '=' . $_POST[$key] : $_POST[$key];
						$hashArray[] = $hashValue . ('InvId' == $key ? ':' . $iaCore->get('robokassa_merchant_password_one') : '');
					}
				}
				$myHash = strtoupper(md5(implode(':', $hashArray)));

				// check signature
				if ($myHash != strtoupper($_POST['SignatureValue']))
				{
					$error = true;
					$messages[] = iaLanguage::get('robokassa_incorrect_hash');
				}
				else
				{
					$iaTransaction = $iaCore->factory('transaction');

					$transaction = $iaTransaction->getBy('sec_key', $_POST['shp_salt']);
					if (!$transaction)
					{
						$error = true;
						$messages[] = iaLanguage::get('robokassa_incorrect_salt');
					}

					$order = array(
						'payment_gross' => $_POST['OutSum'],
						'payment_date' => date('Y-m-d H:i:s'),
						'payment_status' => iaTransaction::PASSED,
						'first_name' => '',
						'last_name' => '',
						'payer_email' => '',
						'txn_id' => '',
						'mc_currency' => '',
					);

					$transaction['status'] = iaTransaction::FAILED;

					if (!$error)
					{
						$transaction['reference_id'] = $_POST['InvId'];
						$transaction['status'] = iaTransaction::PASSED;
						$transaction['amount'] = $_POST['OutSum'];
					}

					if (in_array($transaction['status'], array(iaTransaction::PASSED, iaTransaction::PENDING)))
					{
						// update transaction record
						$iaTransaction->update($transaction, $transaction['id']);

						// process item specific post-processing actions
						$iaPlan = $iaCore->factory('plan');

						$iaPlan->setPaid($transaction);

						// notify admin of a completed payment
						$action = 'payment_completion_admin';
						if ($iaCore->get($action))
						{
							$iaMailer = $iaCore->factory('mailer');

							$iaMailer->loadTemplate($action);
							$iaMailer->addAddress($iaCore->get('site_email'));
							$iaMailer->setReplacements(array(
								'username' => iaUsers::getIdentity()->username,
								'amount' => $transaction['amount'],
								'operation' => $transaction['operation']
							));

							$iaMailer->send();
						}

						// disable debug display
						$iaView->set('nodebug', true);

						if (iaUsers::hasIdentity())
						{
							iaUtil::redirect(iaLanguage::get('thanks'), iaLanguage::get('payment_done'), IA_URL . 'profile/funds/');
						}
						else
						{
							iaUtil::redirect(iaLanguage::get('thanks'), iaLanguage::get('robokassa_payment_done_login'), IA_URL . 'login/');
						}
					}
				}
			}
			else
			{
				$error = true;
				$messages[] = iaLanguage::get('error');
			}

			break;

		case 'fail':

			$error = true;
			$messages[] = 'You cancelled the payment: ' . $_POST['InvId'];

			break;
	}

	$iaView->setMessages($messages, $error ? iaView::ERROR : iaView::SUCCESS);

	$iaView->display($tplFile);
}