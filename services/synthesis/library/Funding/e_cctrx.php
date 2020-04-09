<?php
/**
* A credit card transaction is effected by a processing vendor on our behalf. There are several vendors and perhaps multiple schemes per vendor.
* Credit card transactions can be authorized before they are committed, and they can emark funds before posting.
* There are credit card transactions in which we are the card holder, and there are transactions in which we are the merchant, charging a customer's card.
* This element is for MERCHANT operations, charging and refunding OTHERs cards. We can do simpler purchase transactions for the other kind.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class e_CCTrx extends Element
{
	
}
