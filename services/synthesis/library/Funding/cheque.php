<?php
/**
* A paper check is the instrument of an external funds transfer mediated by our bank and the other entity's bank. 
* It's slow, so there's a latency between the time the disbursar authorizes the transaction and the time it's actually transacted.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class Cheque extends FundsXfer
{
    public static $table = 'fund_cheque';
}

