<?php

/**
 * @file
 * Defines LingotekEntity.
 */
 
/**
 * A class wrapper for Lingotek-specific behavior on nodes.
 */
class LingotekEntity implements LingotekTranslatableEntity {  
  /**
   * A Drupal node.
   *
   * @var object
   */
  protected $entity;
  
  /**
   * The Drupal entity type associated with this class
   */
  protected $entity_type;
  
  /**
   * Lingotek Lingonode properties.
   *
   * @var object
   */
  protected $lingonode;
  
  /**
   * A reference to the Lingotek API.
   *
   * @var LingotekApi
   */
  protected $api = NULL;
  
  public $language = '';

  /**
   * Constructor.
   *
   * This is private since we want consumers to instantiate via the factory methods.
   *
   * @param object $node
   *   A Drupal node.
   */
  private function __construct($entity, $entity_type) {
    $this->entity = $entity;
    $this->nid = $entity->nid;
    $this->language = $entity->language;
    $this->entity_type = $entity_type;
  }
  
  /**
   * Injects reference to an API object.
   *
   * @param LingotekApi $api
   *   An instantiated Lingotek API object.
   */
  public function setApi(LingotekApi $api) {
    $this->api = $api;
  }
  
  /**
   * Factory method for getting a loaded LingotekNode object.
   *
   * @param object $node
   *   A Drupal node.
   *
   * @return LingotekNode
   *   A loaded LingotekNode object.
   */
  public static function load($entity, $entity_type) {
    $entity = new LingotekEntity($entity, $entity_type);
    $entity->setApi(LingotekApi::instance());
    return $entity;
  }
  
  /**
   * Loads a LingotekNode by Lingotek Document ID.
   *
   * @param string $lingotek_document_id
   *   The Document ID whose corresponding node should be loaded.
   *
   * @return mixed
   *   A LingotekNode object on success, FALSE on failure.
   */
  public static function loadByLingotekDocumentId($lingotek_document_id) {
    $node = FALSE;
    
    $query = db_select('lingotek_entity_metadata', 'l')->fields('l');
    $query->condition('entity_type', $this->entity_type);
    $query->condition('entity_key', $key);
    $query->condition('value', $lingotek_document_id);
    $result = $query->execute();

    if ($record = $result->fetchAssoc()) {
      $id = $record['entity_id'];
      $entity_type = $record['entity_type'];
    }
    
    if ($id) {
      $node = self::loadById($id, $entity_type);
    }
    
    return $node;
  }


  /**
   * Gets the Lingotek document ID for this entity.
   *
   * @return mixed
   *   The integer document ID if the entity is associated with a 
   *   Lingotek document. FALSE otherwise.
   */
  public function lingotekDocumentId() {
    return $this->entity->lingotek['document_id'];
  }
  
  /**
   * Gets the contents of this item formatted as XML that can be sent to Lingotek.
   *
   * @return string
   *   The XML document representing the entity's translatable content.
   */
  public function documentLingotekXML() {
    return lingotek_xml_node_body($this->entity_type, $this->entity);
  }  
  
  /**
   * Magic get for access to node and node properties.
   */  
  public function __get($property_name) {
    $property = NULL;
    
    if ($property === 'node') {
      $property = $this->entity;
    }
    elseif (isset($this->entity->$property_name)) {
      $property = $this->entity->$property_name;
    } else { // attempt to lookup the value in the lingonode table
      $val = lingotek_lingonode($this->entity->nid,$property_name); 
      $property = ($val !== FALSE) ? $val : $property;
    } 
    
    return $property;
  }
  

  /**
   * Gets the local Lingotek metadata for this comment.
   *
   * @return array
   *   An array of key/value data for the current comment.
   */
  protected function metadata() {
    $metadata = array();

    $results = db_select('lingotek_entity_metadata', 'meta')
      ->fields('meta')
      ->condition('entity_id', $this->comment->cid)
      ->condition('entity_type', $this->entity_type)
      ->execute();

    foreach ($results as $result) {
      $metadata[$result->entity_key] = $result->value;
    }

    return $metadata;
  }

  /**
   * Gets a Lingotek metadata value for this item.
   *
   * @param string $key
   *   The key whose value should be returned.
   *
   * @return string
   *   The value for the specified key, if it exists.
   */
  public function getMetadataValue($key) {
    return db_select('lingotek_entity_metadata', 'meta')
      ->fields('meta', array('value'))
      ->condition('entity_key', $key)
      ->condition('entity_id', $this->comment->cid)
      ->condition('entity_type', $this->entity_type)
      ->execute()
      ->fetchField();
  }

  /**
   * Sets a Lingotek metadata value for this item.
   *
   * @param string $key
   *   The key for a name/value pair.
   * @param string $value
   *   The value for a name/value pair.
   */
  public function setMetadataValue($key, $value) {
    $metadata = $this->metadata();
    if (!isset($metadata[$key])) {
      db_insert('lingotek_entity_metadata')
        ->fields(array(
          'entity_id' => $this->comment->cid,
          'entity_type' => $this->entity_type,
          'entity_key' => $key,
          'value' => $value,
        ))
        ->execute();

    }
    else {
      db_update('lingotek_entity_metadata')
        ->fields(array(
          'value' => $value
        ))
        ->condition('entity_id', $this->comment->cid)
        ->condition('entity_type', $this->entity_type)
        ->condition('entity_key', $key)
        ->execute();
    }
  }
  
  /**
   * Deletes a Lingotek metadata value for this item
   * 
   * @param string $key
   *  The key for a name/value pair
   */
  public function deleteMetadataValue($key) {
    $metadata = $this->metadata();
    if (isset($metadata[$key])) {
      db_delete('lingotek_entity_metadata')
        ->condition('entity_id', $this->comment->cid)
        ->condition('entity_type', $this->entity_type)
        ->condition('entity_key', $key, 'LIKE')
        ->execute();
    }
  }
  
  /**
   * Updates the local content with data from a Lingotek Document.
   *
   * @return bool
   *   TRUE if the content updates succeeded, FALSE otherwise.
   */
  public function updateLocalContent() {
    
  }
  
  /**
   * Updates the local content of $target_code with data from a Lingotek Document
   *
   * @param string $lingotek_locale
   *   The code for the language that needs to be updated.
   * @return bool
   *   TRUE if the content updates succeeded, FALSE otherwise.
   */
  public function updateLocalContentByTarget($lingotek_locale) {
    // Necessary to fully implement the interface, but we don't do anything
    // on LingotekNode objects, explicitly.
    lingotek_entity_download($this->entity, $this->entity_type, $lingotek_locale);
  }
  
  public function getWorkflowId() {
    return $this->entity->lingotek['workflow_id'];
  }
  
  public function getProjectId() {
    return $this->entity->lingotek['project_id'];
  }
  
  public function getVaultId() {
    return $this->entity->lingotek['vault_id'];
  }
  
  public function getTitle() {
    if ($this->entity_type == 'node') {
      return $this->entity->title;
    } else if ($this->entity_type == 'comment') {
      return $this->entity->subject;
    }
  }
  
  public function getDescription() {
    return $this->getTitle();
  }
  
    /**
   * Return the Drupal Entity type
   *
   * @return string
   *   The entity type associated with this object
   */
  public function getEntityType() {
    return $this->entity_type;
  }

  /**
   * Return the node ID
   *
   * @return int
   *   The ID associated with this object
   */
  public function getId() {
    return $this->entity->nid;
  }
  
  public function getSourceLocale() {
    return Lingotek::convertDrupal2Lingotek($this->entity->language);
  }
}
