<?php
/**
 * @file
 * Content translation management and sync functions.
 */

// -- Node Sync Statuses (Main Node Level) --
define('LINGOTEK_NODE_SYNC_STATUS_CURRENT', 'CURRENT'); // Node and all target languages in sync.
define('LINGOTEK_NODE_SYNC_STATUS_EDITED',  'EDITED');  // Node and one or more target languages out of sync.
define('LINGOTEK_NODE_SYNC_STATUS_PENDING', 'PENDING'); // Node 

// -- Target Sync Statuses (Node Language Level) --
define('LINGOTEK_TARGET_SYNC_STATUS_CURRENT', 'CURRENT'); // Node target language in sync.
define('LINGOTEK_TARGET_SYNC_STATUS_EDITED',  'EDITED'); // Node target language in waiting for the node to be uploaded.
define('LINGOTEK_TARGET_SYNC_STATUS_PENDING', 'PENDING'); // Node target language out of sync.  The source language has been sent for translation, but the translated version has not been downloaded.
define('LINGOTEK_TARGET_SYNC_STATUS_LOCKED',  'LOCKED');  // Node target language is locked, and will NOT be replaced.




/**
 *  Gets the Node sync status.
**/
function lingotek_get_node_sync_status( $node_id ) {

  return lingotek_lingonode( $node_id, 'node_sync_status' );

}

/**
 *  Sets the Node sync status.
**/
function lingotek_set_node_sync_status( $node_id, $node_status ) {

  lingotek_lingonode( $node_id, 'node_sync_status', $node_status );

}





/**
 *  Gets the Node Target language status.
**/
function lingotek_get_target_sync_status( $node_id, $target_language ) {

  $key = 'target_sync_status_' . $target_language;
  return lingotek_lingonode( $node_id, $key );

}

/**
 *  Sets the Node Target language to the specified value.
**/
function lingotek_set_target_sync_status( $node_id, $target_language, $node_status ) {

  $key = 'target_sync_status_' . $target_language;
  lingotek_lingonode( $node_id, $key, $node_status );

}



// ----  Combo Functions

/**
 *  Sets the Node and Target languages to a given value.
 *  Note:  All targets get the SAME status.
**/
function lingotek_set_node_and_targets_sync_status( $node_id, $node_status, $targets_status ) {

watchdog( 'lingotek_batch_mt', 'SYNC FLAGS 1', array(), WATCHDOG_DEBUG );

  // Set the Node to EDITED.
  lingotek_set_node_sync_status( $node_id, $node_status );

watchdog( 'lingotek_batch_mt', 'SYNC FLAGS 2', array(), WATCHDOG_DEBUG );

  // Loop though each target language, and set that target to EDITED.
  $languages = lingotek_get_target_languages( TRUE );
  foreach( $languages as $language ) {
    lingotek_set_target_sync_status( $node_id, $language, $targets_status );
watchdog( 'lingotek_batch_mt', 'SYNC FLAGS 3 - @lang', array( '@lang' => $language ), WATCHDOG_DEBUG );
  }
watchdog( 'lingotek_batch_mt', 'SYNC FLAGS 4', array(), WATCHDOG_DEBUG );

}




/**
 *  Gets the nodes current sync status array.
**/
function lingotek_get_node_and_target_sync_status( $node_id ) {

  $result = array();

  $result[ 'node_sync_status' ] = lingotek_get_node_sync_status( $node_id );

  // Loop though each target language, and set that target to EDITED.
  $languages = lingotek_get_target_languages( TRUE );
  foreach( $languages as $language ) {
    $result[ 'targets' ][ $language ] = lingotek_get_target_sync_status( $node_id, $language );
  }

  /* -- This will be the sync status array format
  array (
    'node_sync_status' => 'EDITED',
    'targets' => 
    array (
      'es' => 'EDITED',
      'pt' => 'CURRENT',
    ),
  )
  */

  return $result;

}


/**
 *  Sets the Node and its Target languages to a given value.
 *  Note:  Works off a node status array, so the Node and Targets may have different values.
**/
function lingotek_set_node_and_targets_sync_status_by_array( $node_id, array $node_status_array ) {

  // Set the Node to EDITED.
  lingotek_set_node_sync_status( $node_id, $node_status_array[ 'node_sync_status' ] );


  // Loop though each target language, and set that target.
  foreach( $node_status_array[ 'targets' ] as $language_code => $status ) {
    lingotek_set_target_sync_status( $node_id, $language_code, $status );
  }

}



/**
 *  When a new MT language is added, add a Target status tag to every node.
**/
function lingotek_add_target_sync_status_to_all_nodes( $drupal_language_code ) {

  $query = db_select( 'lingotek', 'l' )->fields( 'l' );
  $query->condition( 'lingokey', 'node_sync_status' );
  $result = $query->execute();

  while ( $record = $result->fetchAssoc() ) {
    // If the Node is CURRENT or PENDING, then we just need to pull down the new translation (because the source will have been uploaded), so set the Target to PENDING.
    if( ($record[ 'lingovalue' ] == LINGOTEK_NODE_SYNC_STATUS_CURRENT ) || ( $record[ 'lingovalue' ] == LINGOTEK_NODE_SYNC_STATUS_PENDING ) ) {
      lingotek_set_target_sync_status( $record[ 'nid' ], $drupal_language_code, LINGOTEK_TARGET_SYNC_STATUS_PENDING );
    }
    else { // Otherwise, set it to EDITED
      lingotek_set_target_sync_status( $record[ 'nid' ], $drupal_language_code, LINGOTEK_TARGET_SYNC_STATUS_EDITED );
    }
  }

}

/**
 *  When a new MT language is added, add a Target status tag to every node.
**/
function lingotek_delete_target_sync_status_for_all_nodes( $drupal_language_code ) {

  $key = 'target_sync_status_' . $drupal_language_code;
  db_delete('lingotek')->condition( 'lingokey', $key )->execute();

}








/**
 * Returns the site callback url.
**/
function lingotek_get_notifications_url() {
  global $base_url;
  return $base_url . '/' . LINGOTEK_NOTIFICATIONS_URL;
}


/**
 *
 * Format:  ?doc_id={document_id}&target_code={target_language}&project_id={project_id}
**/
function lingotek_notifications() {

  $document_id = $_GET[ 'doc_id' ];
  $language_code = $_GET[ 'target_code' ];
  $project_id = $_GET[ 'project_id' ];

  $drupal_language_code = lingotek_drupal_language( $language_code );

  if ( isset( $document_id ) ) {

    include_once('lib/Drupal/batch/lingotek.mt.batch.download.inc');
    $context = '';

    $nid = lingotek_get_node_id_from_document_id( $document_id );
    lingotek_download_node_machine_translation( $nid, $drupal_language_code, $context );

    watchdog( 'lingotek_callback', '
      <strong>NID:</strong> @node_id
      <br /><strong>DocumentID:</strong> @document_id
      <br /><strong>LanguageCode:</strong> @language_code
      <br /><strong>ProjectID:</strong> @project_id', 
      array(
        '@node_id' => $nid,
        '@document_id' => $document_id,
        '@language_code' => $drupal_language_code,
        '@project_id' => $project_id
    ), WATCHDOG_DEBUG );

  }

}

/*
6	document_id_en	111615
6	node_sync_status	PENDING
6	project_id	20342
6	target_sync_status_es	PENDING
6	target_sync_status_it	PENDING
6	vault_id	8787
*/



/**
 * Finds the Node ID when given a Document ID.
 * Returns FALSE if not found.
**/
function lingotek_get_node_id_from_document_id( $document_id ) {

  $found = FALSE;
  $source_language = lingotek_get_source_language();
  $key = 'document_id_' . $source_language;

  $query = db_select( 'lingotek', 'l' )->fields( 'l' );
  $query->condition( 'lingokey', $key );
  $query->condition( 'lingovalue', $document_id );
  $result = $query->execute();

  if ( $record = $result->fetchAssoc() ) {
    $found = $record[ 'nid' ];
  }

  return $found;

}


