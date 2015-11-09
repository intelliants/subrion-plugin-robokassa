<?php
//##copyright##

// generate mandatory params
$formValues['MrchLogin'] = $iaCore->get('robokassa_merchant_login');
$formValues['OutSum'] = $plan['cost'];
$formValues['InvId'] = 0;
$formValues['Desc'] = $plan['title'];
$formValues['Culture'] = $iaCore->get('robokassa_language');

// generate custom params
if (iaUsers::hasIdentity())
{
	$formValues['Email'] = iaUsers::getIdentity()->email;
	$formValues['shp_member'] = iaUsers::getIdentity()->id;
}
$formValues['shp_salt'] = $transaction['sec_key'];

// generate security hash
$hashArray = array();
foreach ($formValues as $key => $value)
{
	if (in_array($key, array('Desc', 'Culture', 'Email')))
	{
		continue;
	}
	$hashValue = ('shp_' == substr($key, 0, 4)) ? $key . '=' . $value : $value;
	$hashArray[] = $hashValue . ('InvId' == $key ? ':' . $iaCore->get('robokassa_merchant_password_one') : '');
}
$formValues['SignatureValue'] = md5(implode(':', $hashArray));

$iaView->assign('formValues', $formValues);