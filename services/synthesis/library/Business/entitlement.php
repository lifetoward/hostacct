<?php
/**
* An Entitlement is a key element of what's been referred to as "chit" accounting.
* It is a charge which entitles the recipient to a defined quantity of a particular deliverable within a specified period of time.
* Entitlements are the charges associated with bundles (which are billables designed to be charged as entitlements),
* subscriptions (which aren't billable but designate a billable which charges as an entitlement),
* and even service relationships which are secured by the receipt of some deposit.
*/
class Entitlement extends Element
{
	public static $table = "biz_entitlement", $singular = "Entitlement", $plural = "Entitlements", $descriptive = "Entitlement details"
		,$fielddefs = array(
			 'charge'=>array('name'=>'charge', 'class'=>'e_charge', 'type'=>'include', 'sort'=>true, 'identifying'=>true)
			,'recipient'=>array('name'=>'recipient', 'class'=>'e_recipient', 'type'=>'belong', 'sort'=>true, 'identifying'=>true)
			,'deliverable'=>array('name'=>'deliverable', 'class'=>'e_deliverable', 'type'=>'belong', 'sort'=>true)
			,'begins'=>array('name'=>'begins', 'class'=>'t_date', 'label'=>'Begins', 'sort'=>'DESC', 'identifying'=>true, 'required'=>true)
			,'ends'=>array('name'=>'ends', 'class'=>'t_date', 'label'=>'Ends', 'sort'=>'DESC',
				'help'=>"The last day for which deliverables may be covered by this entitlement. If left unspecified, it means the entitlement remains in place indefinitely.")
			,'coverrate'=>array('name'=>'coverrate', 'class'=>'t_dollars', 'label'=>'Covered price', 'initial'=>0, 'required'=>true,
				'help'=>"The price per unit of deliverables which are covered by this entitlement.")
			,'addonrate'=>array('name'=>'addonrate', 'class'=>'t_dollars', 'label'=>'Add-on price',
				'help'=>"The price per unit of deliverables which fall within the time range of this entitlement but which exceed the entitled quantity."
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>array(
				 'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete") )
		), $actions = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'display'=>array('action'=>'a_Display', 'role'=>array('*owner','Staff'))
			,'update'=>array('action'=>'a_Edit', 'role'=>array('*owner','Staff'))
			,'delete'=>array('action'=>'a_Delete','role'=>'Administrator')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Staff')
			,'list'=>array('action'=>'a_Browse', 'role'=>'Staff')
		);


}
