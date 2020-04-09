<?php
/**
* The generic transaction handler does display, create, and update operations for any transaction.
*
* While in the browser, initial and ongoing calculations are made against the entries for validity, deletion, trx balance, etc.
* When the form posts, and this may be because a subaction is being invoked, we always just make the instance entries match the form as posted, even if invalid or out of balance.
* We only care about completeness in 2 places: In the browser when allowing the POST button to be active, and in the action when attempting to store the transaction.
* This means entry counts, sums, and balancing is irrelevant except when attempting to finalize a transaction. This means a lot of logic can be dropped from older approaches.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class a_Trx extends Action
{
	private $trx;
	private static $savedProperties = ['trx'];
	use SerializationHelper;

	public function __construct( Context $c, array $args = null )
	{
		parent::__construct($c);
		$class = $args['class'] ? $args['class'] : 'Transaction';
		if (is_array($args)) {
			try { $this->trx = $class::get($args); }
			catch (Exception $ex) { }
		}
		// We are able to render Elements which include Transaction as a subElement in addition to extending the class itself while using the same table structure; the latter case is automatic in Element load processing.
		if ($this->trx && $this->trx->class != $this->trx) // The trx->class is defined as "derived=>{}._capcel" which has special logic in Element to produce the encapsulating object.
			$this->trx = $this->trx->class; // we swap over to the encapsulating Element
		if (!$this->trx)
			$this->trx = $class::create(array_merge(['trxdate'=>date('Y-m-d')], $args)); // i.e. initialize with today
		$this->context = new SubContext($c);
		foreach ((array)$this->trx->entries as $entry)
			$entry->disposition = 'keep';
	}

	public function render_me( Result $returning = null )
	{
		// SETUP
		extract($this->context->request);
		$this->trx->load(); // no-op when not stored
		$trxclass = get_class($this->trx);
		$hints = isset($trxclass::$hints) && is_array($trxclass::$hints) ? $trxclass::$hints[__CLASS__] : [];
		$deleteRole = isset($trxclass::$operations) ? $trxclass::$operations['delete']['role'] : null;
		$R = new TrxRendering($this->context, $returning, $this->trx);

		// RETURNING FROM A SUB-ELEMENT CREATE, ETC.
		if ($returning && ($nfn = $this->trx->reffield_adding)) { // assignment intended
			if ($returning instanceof Notice && $returning->reason == 'success' && get_class($returning->focus) == $this->trx->{¶.$nfn}['class'])
				// We already have the unvalidated posting from when we triggered the subElement create. So here we just add the newly created subElement to that posting.
				$this->trx->unvalidated = array_merge($this->trx->unvalidated, [$nfn=>$returning->focus->_id]); // auxiliary arrays cannot be updated per-key
			unset($this->trx->reffield_adding);
			$post = []; // no posting allowed as we return from a subElement create
		}

		// TRIGGER PROCESSING...

		// TRIGGERED SUB-ELEMENT CREATE OPERATIONS
		else if ($args['operation'] == 'create' && in_array($args['class'], ['Project','ClientItem'])) {
			$this->trx->unvalidated = $post;
			$this->trx->reffield_adding = $args['reffield']; // We save this information to know what got added when the subaction returns
			return static::setSubOperation($args);
		}

		// DELETE REQUEST
		else if ($args['operation'] == 'delete' && $this->context->isAuthorized($deleteRole)) {
			try {
				$id = $this->trx->delete();
			} catch (Exception $ex) {
				return new Notice('The system failed to delete the transaction. '. $ex->getMessage(), 'failure', $returning, $this->trx);
			}
			return new Notice('The system deleted the transaction.', 'success', $returning, $this->trx); // note that this transaction will be unstored
		}

		// SUBMITTED DATA, WHETHER IMMEDIATE OR AFTER RETURNING
		// In most cases (all except those above), we need to accept what was posted along with the request (if anything)
		if (is_array($this->trx->unvalidated))
			$post = array_merge($this->trx->unvalidated, (array)$post);
		if (count($post)) {
			try {
				$this->trx->unvalidated = $post;
				$this->trx->populateTrx($post, $this->context);
				unset($this->trx->unvalidated);
			} catch (BadFieldValuesX $ex) {
				foreach ($ex->problems as $fn=>$problem)
					$problems .= "\n<dt>{$this->trx->{¬.$fn}}</dt><dd>". htmlentities($problem) ."</dd>";
				$R->addResult(new Notice("<p>There were problems with the data we received:</p><dl>$problems\n</dl><p>Please check your entries and try again.</p>", 'failure', null, null, 'html'));
			}
		}
		// The action's job is to keep the trx and its entry objects current with the user's input, even if the transaction is not complete or balanced yet.
		// Note that while under user edit, some of the entries may be marked deleted, or they may be incomplete or otherwise invalid.
		// The interaction still needs to be aware of when balanced so that posting becomes enabled, but this goes on in the browser.

		// SUBMIT-TO-STORE
		if (!$this->trx->unvalidated && $args['operation'] == 'store') {
			try {
				$this->trx->store();
				return new Notice("Stored transaction with {$this->trx->°creditcount} credits and {$this->trx->°debitcount} debits and a volume of {$this->trx->°creditsum}.", 'success', $returning, $this->trx);
			} catch (dbDuplicateDataX $ex) {
				$R->addResult(new Notice("<p>The system is unable to post the transaction as submitted.</p>".
					'<p>Each transaction must be unique in its combination of date and description. '.
					'You may want to alter the description in order to meet this requirement.</p>', 'failure', null, null, 'html'));
			} catch (Exception $ex) {
				$R->addResult(new Notice($ex, 'error'));
			}
		}

		// ITERATE, ie MAKE A NEW DUPLICATE
		else if ($args['operation'] == 'iterate' && $hints['iterable']) {
			$this->trx = $this->trx->duplicate();
			$this->trx->trxdate = date("Y-m-d");
			$this->trx->_rendering = null;
			$R->addResult(new Notice('A NEW transaction has been created based on the former. The date has been reset to today.'));
			// fall thru to render the new transaction
		}

		// RENDERING

		// Action Triggers
		if (!$this->context->readonly)
			$R->addFocalTrigger('store', true, 301);
		if ($this->trx->_stored) {
			if ($hints['iterable'])
				$R->addFocalTrigger('iterate');
			if ($this->status < AcctEntry::Confirmed && $this->context->isAuthorized($deleteRole))
				$R->addFocalTrigger('delete');
		}
		$R->triggers[] = $R->renderCancelButton(['label'=>'Cancel', 'size'=>'lg']);

		// Trx Subclass actual UI rendering
		$R->tabindex = 200;
		$this->trx->renderTrx($R); // May pre-populate some of the result rendering, and definitely some of the ancillaries.
		return $R;
	}

} // class a_Trx
