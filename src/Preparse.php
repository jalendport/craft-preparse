<?php
namespace jalendport\Preparse;

use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use yii\base\Event;

/**
 * @method Settings getSettings()
 */
class Preparse extends Plugin
{

	public ?string $changelogUrl = 'https://github.com/besteadfast/craft-preparse-field/blob/master/CHANGELOG.md';
	public bool $hasCpSection = false;
	public bool $hasCpSettings = false;
	public string $schemaVersion = "4.0.0.0";

	public function init()
	{

		parent::init();

		Event::on(
			Fields::class,
			Fields::EVENT_REGISTER_FIELD_TYPES,
			function(RegisterComponentTypesEvent $event) {
				$event->types[] = PreparseField::class;
			}
		);

	}

}
