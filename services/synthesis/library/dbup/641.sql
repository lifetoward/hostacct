-- DBUP for revision 641

UPDATE actg_trx SET _capcel = REPLACE(_capcel, 'RebateTrx=', 'RecoveryTrx=');
