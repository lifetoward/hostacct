<?php
/**
* A balance is a large, SIGNED sum of dollar amounts.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_balance extends t_dollars
{
	const FORMAT='10.2', MIN='struct';
}
