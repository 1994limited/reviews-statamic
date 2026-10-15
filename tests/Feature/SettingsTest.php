<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Settings;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Addon;

class SettingsTest extends TestCase
{
    protected function save(array $values): void
    {
        Addon::get(Settings::ADDON)->settings()->set($values)->save();
        Settings::flush();
    }

    #[Test]
    public function the_addon_has_a_settings_blueprint(): void
    {
        $blueprint = Addon::get(Settings::ADDON)->settingsBlueprint();

        $this->assertNotNull($blueprint);

        foreach (['collections', 'who', 'auto_approve', 'criteria', 'photos_enabled', 'notify_to', 'requests_enabled'] as $field) {
            $this->assertNotNull($blueprint->field($field), "No [{$field}] setting.");
        }
    }

    #[Test]
    public function saved_settings_win_over_config(): void
    {
        config(['reviews.auto_approve' => 'users', 'reviews.form.who' => 'anyone']);

        $this->assertSame('users', Settings::string('auto_approve'));

        $this->save(['auto_approve' => 'never', 'who' => 'users', 'notify_to' => ['a@example.com'], 'photos_enabled' => false]);

        $this->assertSame('never', Settings::string('auto_approve'));
        $this->assertSame('users', Settings::string('who'));
        $this->assertSame(['a@example.com'], Settings::list('notify_to'));
        $this->assertFalse(Settings::bool('photos_enabled'));
    }

    #[Test]
    public function an_empty_setting_falls_back_to_config(): void
    {
        config(['reviews.collections' => ['products']]);

        $this->save(['collections' => null, 'request_subject' => '']);

        $this->assertSame(['products'], Settings::list('collections'));
        $this->assertNull(Settings::string('request_subject'));
    }

    #[Test]
    public function criteria_from_settings_are_used(): void
    {
        $this->product();
        $this->save(['criteria' => [
            ['label' => 'Story', 'handle' => 'story'],
            ['label' => 'Broken', 'handle' => 'Not A Handle'],
            ['label' => 'Recipes only', 'handle' => 'taste', 'collections' => ['recipes']],
        ]]);

        $criteria = $this->reviews()->criteria(\Statamic\Facades\Entry::find('book'));

        $this->assertSame([['handle' => 'story', 'label' => 'Story']], $criteria);
    }

    #[Test]
    public function saving_the_settings_takes_effect_straight_away(): void
    {
        $this->product();
        Settings::get('who');
        Addon::get(Settings::ADDON)->settings()->set(['who' => 'users'])->save();

        $this->post('/!/reviews/submit', $this->submission())->assertSessionHasErrors('review', null, 'reviews');
    }
}
