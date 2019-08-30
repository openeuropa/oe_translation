<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Behat;

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\node\NodeInterface;
use Drupal\tmgmt\Entity\Job;
use EC\Poetry\Messages\Components\Identifier;

/**
 * Context specific to TMGMT-based poetry translation.
 */
class PoetryTranslationContext extends RawDrupalContext {

  /**
   * Installs the mock module.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeFeatureScope $scope
   *   The scope.
   *
   * @BeforeFeature @poetry
   */
  public static function installMockModule(BeforeFeatureScope $scope) {
    \Drupal::service('module_installer')->install(['oe_translation_poetry_mock']);
  }

  /**
   * Uninstalls the mock module.
   *
   * @param \Behat\Behat\Hook\Scope\AfterFeatureScope $scope
   *   The scope.
   *
   * @AfterFeature @poetry
   */
  public static function uninstallMockModule(AfterFeatureScope $scope) {
    // \Drupal::service('module_installer')->uninstall(['oe_translation_poetry_mock']);
  }

  /**
   * Selects one or more languages from the translation overview page.
   *
   * @param string $languages
   *   The languages.
   *
   * @Given I select the language(s) :languages in the language list
   */
  public function iSelectTheLanguages(string $languages): void {
    $languages = $this->getLanguagesFromNames($languages);
    if (!$languages) {
      throw new \Exception('The specified languages cannot be found');
    }

    $langcodes = array_keys($languages);
    foreach ($langcodes as $langcode) {
      $this->getSession()->getPage()->checkField("languages[$langcode]");
    }
  }

  /**
   * Checks that the Poetry jobs for a given node got created for the languages.
   *
   * @param string $title
   *   The node title.
   * @param string $languages
   *   The languages.
   *
   * @Then the Poetry request jobs to translate :title should get created for :languages
   */
  public function thePoetryRequestJobsShouldGetCreated(string $title, string $languages): void {
    $languages = $this->getLanguagesFromNames($languages);
    $node = $this->getNodeByTitle($title);
    $query = $this->getEntityJobsQuery($node);
    $query->isNull('job.poetry_state');
    $result = $query->execute()->fetchAllAssoc('target_language');
    if (count(array_intersect_key($result, $languages)) !== count($languages)) {
      throw new \Exception('The jobs have not been created for these languages.');
    }
  }

  /**
   * Accepts the translation jobs.
   *
   * This mimics a notification that has come from the Poetry service which
   * contains an acceptance status notification.
   *
   * @param string $title
   *   The node title.
   * @param string $languages
   *   The languages.
   *
   * @Given the Poetry translation request of :title in :languages gets accepted
   */
  public function thePoetryTranslationRequestGetAccepted(string $title, string $languages): void {
    $languages = $this->getLanguagesFromNames($languages);
    $node = $this->getNodeByTitle($title);
    $query = $this->getEntityJobsQuery($node);
    $query->condition('job.target_language', array_keys($languages), 'IN');
    $query->isNull('job.poetry_state');
    $result = $query->execute()->fetchAllAssoc('tjid');
    $jobs = Job::loadMultiple(array_keys($result));
    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = reset($jobs);

    $accepted = [];
    foreach ($languages as $language) {
      $accepted[] = [
        'code' => strtoupper($language->getId()),
        'date' => '30/08/2050 23:59',
        'accepted_date' => '30/09/2050 23:59',
      ];
    }
    $status_notification = \Drupal::service('oe_translation_poetry_mock.fixture_generator')->statusNotification($job->get('poetry_request_id')->first()->getValue(), 'ONG', $accepted);

    $this->performNotification($status_notification);
  }

  /**
   * Receives the translations.
   *
   * This mimics a notification that has come from the Poetry service which
   * contains the translated content.
   *
   * @param string $title
   *   The node title.
   * @param string $languages
   *   The languages.
   *
   * @Given the Poetry translation(s) of :title in :languages get sent by Poetry
   */
  public function thePoetryTranslationRequestGetSent(string $title, string $languages): void {
    $languages = $this->getLanguagesFromNames($languages);
    $node = $this->getNodeByTitle($title);
    $query = $this->getEntityJobsQuery($node);
    $query->condition('job.poetry_state', 'ongoing');
    $query->condition('job.target_language', array_keys($languages), 'IN');
    $result = $query->execute()->fetchAllAssoc('tjid');
    $jobs = Job::loadMultiple(array_keys($result));

    /** @var \Drupal\tmgmt\JobInterface $job */
    foreach ($jobs as $job) {
      $items = $job->getItems();
      $item = reset($items);

      $data = \Drupal::service('tmgmt.data')->filterTranslatable($item->getData());
      foreach ($data as $field => &$info) {
        $info['#text'] .= ' - ' . $job->getTargetLangcode();
      }

      $identifier = new Identifier();
      foreach ($job->get('poetry_request_id')->first()->getValue() as $name => $value) {
        $identifier->offsetSet($name, $value);
      }
      $translation_notification = \Drupal::service('oe_translation_poetry_mock.fixture_generator')->translationNotification($identifier, $job->getTargetLangcode(), $data, (int) $item->id(), (int) $job->id());
      $this->performNotification($translation_notification);
    }
  }

  /**
   * Fills in a field whose label might be found more than one time.
   *
   * @param string $count
   *   The count, for example "first", "second", etc.
   * @param string $name
   *   The field name.
   * @param string $value
   *   The field value.
   *
   * @Given I fill in the the :count :name field with :value
   */
  public function iFillInTheTheFieldWith(string $count, string $name, string $value): void {
    $count_map = [
      'first' => 0,
      'second' => 1,
      'third' => 2,
      'fourth' => 4,
      'fifth' => 5,
    ];
    $fields = $this->getSession()->getPage()->findAll('named', ['field', $name]);
    if (!$fields) {
      throw new \Exception(sprintf('The %s field could not be found.', $name));
    }

    if (!isset($fields[$count_map[$count]])) {
      throw new \Exception('There is no "%s" field by the name of %s.', $count, $name);
    }

    $fields[$count_map[$count]]->setValue($value);
  }

  /**
   * Returns the language objects based on their names.
   *
   * @param string $names
   *   The language names.
   *
   * @return \Drupal\language\ConfigurableLanguageInterface[]
   *   The language objects.
   */
  protected function getLanguagesFromNames(string $names): array {
    $language_names = explode(', ', $names);
    return \Drupal::entityTypeManager()->getStorage('configurable_language')->loadByProperties(['label' => $language_names]);
  }

  /**
   * Prepares and returns a query for the jobs of a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The query.
   */
  protected function getEntityJobsQuery(ContentEntityInterface $entity): SelectInterface {
    $query = \Drupal::database()->select('tmgmt_job', 'job');
    $query->join('tmgmt_job_item', 'job_item', 'job.tjid = job_item.tjid');
    $query->fields('job', ['tjid', 'target_language']);
    $query->condition('job_item.item_id', $entity->id());
    $query->condition('job.translator', 'poetry', '=');
    return $query;
  }

  /**
   * Returns a node by a given title.
   *
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function getNodeByTitle(string $title): NodeInterface {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => $title]);
    if (!$nodes) {
      throw new \Exception('The specified content cannot be found');
    }

    return reset($nodes);
  }

  /**
   * Calls the notification endpoint with a message.
   *
   * This mimics notification requests sent by Poetry.
   *
   * @param string $message
   *   The message.
   *
   * @return string
   *   The response XML.
   */
  protected function performNotification(string $message): string {
    $settings = \Drupal::service('oe_translation_poetry.client.default')->getSettings();
    $credentials['username'] = $settings['notification.username'];
    $credentials['password'] = $settings['notification.password'];

    $url = $this->locatePath('poetry/notifications');
    $client = new \SoapClient($url . '?wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
    return $client->__soapCall('handle', [
      $settings['notification.username'],
      $settings['notification.password'],
      $message,
    ]);
  }

}
