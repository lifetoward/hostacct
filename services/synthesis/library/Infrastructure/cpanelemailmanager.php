<?php
namespace Lifetoward\Synthesis\Infrastructure;
/**
* A specific Email Management implementation via Control Panel (as at Bluehost)
*/
class CPanelEmailManager extends EmailManager
{
	private $secrets = [ 'host'=>'https://box1253.bluehost.com:2083', 'prefix'=>'frontend/bluehost', 'account'=>'livetowa', 'password'=>'quero;LG3chuoH' ];
/**
*
# BlueHost functionality script

# We can delete forwarders too:
# https://lifetoward.com:2083/frontend/bluehost/mail/dodelfwd.html?email={urlencode($alias)}&emaildest={urlencode($target)}
# frontend/bluehost/mail/fwds.html?domain={domain}&&itemsperpage=10000&page=1&searchregex=
# We can delete forwarders too:
# https://lifetoward.com:2083/frontend/{theme}/mail/dodelfwd.html?email={urlencode($alias)}&emaildest={urlencode($target)}
*/
	public function listEmailAliases( $filter )
	{
		$cpanel = new CPanelUIConnection($this->secrets);
		$result = $cpanel->get('mail/fwds.html', [ 'domain'=>"$this", 'itemsperpage'=>10000, 'searchregex'=>$filter, 'page'=>1 ]);
			// unused parms: api2_paginate_start=1&api2_sort_column=&api2_sort_reverse=0&skip=-1
		preg_match();
		return [ [ 'domain'=>"$this", 'address'=>$address, 'target'=>$target ], [] ];
	}

	public function addEmailAlias( $address, $target )
	{
		$cpanel = new CPanelUIConnection($this->secrets);
		$result = $cpanel->get('mail/doaddfwd.html', [ 'domain'=>"$this", 'email'=>"$address", 'fwdopt'=>'fwd', 'fwdemail'=>"$target" ]);
		preg_match();
		if ($bad)
			throw new Exception();
	}

	public function dropEmailAlias( $address, $target )
	{
		$cpanel = new CPanelUIConnection($this->secrets);
		$result = $cpanel->get('mail/dodelfwd.html', [ 'email'=>"$address@$domain", 'emaildest'=>"$target" ]);
		preg_match();
		if ($bad)
			throw new Exception();
	}
}

