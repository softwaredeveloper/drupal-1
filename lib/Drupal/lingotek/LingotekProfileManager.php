<?php

/**
 * @file
 * Defines LingotekProfileManager
 */

/**
 * A class wrapper for Lingotek Profiles
 */
class LingotekProfileManager {

  protected static $profiles;

  public static function getProfiles() {
    if (!empty(self::$profiles)) {
      return self::$profiles;
    }
    self::$profiles = variable_get('lingotek_profiles', array());

    if (empty($profiles)) {
      self::$profiles[] = array(
        'name' => 'Automatic',
        'create_lingotek_document' => 1,
        'sync_method' => 1,
      );
      self::$profiles[] = array(
        'name' => 'Manual',
        'create_lingotek_document' => 0,
        'sync_method' => 0,
      );
      variable_set('lingotek_profiles', self::$profiles);
    }
    return self::$profiles;
  }

  public static function loadByName($profile_name) {
    self::$profiles = variable_get('lingotek_profiles', array());
    foreach (self::$profiles as $profile_id => $profile) {
      if ($profile['name'] == $profile_name) {
        return new LingotekProfileManager($profile_id);
      }
    }
    throw new LingotekException('Unknown profile name: ' . $profile_name);
  }

  public static function loadByBundle($entity_type, $bundle, $source_locale = NULL) {
    $entity_profiles = variable_get('lingotek_entity_profiles', array());
    try {
      return new LingotekProfileManager($entity_profiles[$entity_type][$bundle]);
    }
    catch (Exception $e) {
      // TODO: add the profile default for the bundle
    }
  }

}
