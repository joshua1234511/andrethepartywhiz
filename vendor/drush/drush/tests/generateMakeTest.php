<?php

namespace Unish;

/**
 * Generate makefile tests
 *
 * @group make
 * @group slow
 */
class generateMakeCase extends CommandUnishTestCase {
  function testGenerateMake() {
    return $this->_testGenerateMake('devel', 'bootstrap');
  }

  function testGenerateMakeOmega() {
    # TODO: Don't skip this test by default once the underlying issue is resolved.
    # See: https://github.com/drush-ops/drush/issues/2030
    $run_omega_make_test = getenv("DRUSH_TEST_MAKE_OMEGA");
    if ($run_omega_make_test) {
      return $this->_testGenerateMake('devel', 'omega');
    }
    else {
      $this->markTestSkipped('Set `DRUSH_TEST_MAKE_OMEGA=1`, in order to run this test. See: https://github.com/drush-ops/drush/issues/2028');
    }
  }

  function _testGenerateMake($module, $theme) {
    $sites = $this->setUpDrupal(1, TRUE);
    $major_version = UNISH_DRUPAL_MAJOR_VERSION . '.x';

    $options = array(
      'yes' => NULL,
      'pipe' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
      'cache' => NULL,
      'strict' => 0, // Don't validate options
    );
    // Omega requires these core modules.
    $this->drush('pm-enable', array('block', 'search', 'help'), $options);
    $this->drush('pm-download', array($theme, $module), $options);
    $this->drush('pm-enable', array($theme, $module), $options);

    $makefile = UNISH_SANDBOX . '/dev.make.yml';

    // First generate a simple makefile with no version information
    $this->drush('generate-makefile', array($makefile), array('exclude-versions' => NULL) + $options);
    $expected = <<<EOD
core: $major_version
api: 2
projects:
  drupal: {  }
  $module: {  }
  $theme: {  }
EOD;
    $actual = trim(file_get_contents($makefile));

    $this->assertEquals($expected, $actual);

    // Next generate a simple makefile with no version information in .ini format
    $makefile = UNISH_SANDBOX . '/dev.make';
    $this->drush('generate-makefile', array($makefile), array('exclude-versions' => NULL, 'format' => 'ini') + $options);
    $expected = <<<EOD
; This file was auto-generated by drush make
core = $major_version
api = 2

; Core
projects[] = "drupal"
; Modules
projects[] = "$module"
; Themes
projects[] = "$theme"
EOD;
    $actual = trim(file_get_contents($makefile));

    $this->assertEquals($expected, $actual);

    // Download a module to a 'contrib' directory to test the subdir feature
    mkdir($this->webroot() + '/sites/all/modules/contrib');
    $this->drush('pm-download', array('libraries'), array('destination' => 'sites/all/modules/contrib') + $options);
    $this->drush('pm-enable', array('libraries'), $options);
    $makefile = UNISH_SANDBOX . '/dev.make.yml';
    $this->drush('generate-makefile', array($makefile), array('exclude-versions' => NULL) + $options);
    $expected = <<<EOD
core: $major_version
api: 2
projects:
  drupal: {  }
  $module: {  }
  libraries:
    subdir: contrib
  $theme: {  }
EOD;
    $actual = trim(file_get_contents($makefile));

    $this->assertEquals($expected, $actual);

    // Again in .ini format.
    $makefile = UNISH_SANDBOX . '/dev.make';
    $this->drush('generate-makefile', array($makefile), array('exclude-versions' => NULL, 'format' => 'ini') + $options);
    $expected = <<<EOD
; This file was auto-generated by drush make
core = $major_version
api = 2

; Core
projects[] = "drupal"
; Modules
projects[] = "$module"
projects[libraries][subdir] = "contrib"

; Themes
projects[] = "$theme"
EOD;
    $actual = trim(file_get_contents($makefile));

    $this->assertEquals($expected, $actual);

    // Generate a makefile with version numbers (in .ini format).
    $this->drush('generate-makefile', array($makefile), array('format' => 'ini') + $options);
    $actual = file_get_contents($makefile);
    $this->assertContains('projects[' . $module . '][version] = "', $actual);
  }
}
