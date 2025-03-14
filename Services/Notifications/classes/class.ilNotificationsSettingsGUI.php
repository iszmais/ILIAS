<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\DI\Container;
use ILIAS\Notifications\Repository\PushRepository;
use ILIAS\UI\Component\Input\Container\Form\Form;

/**
 * @ilCtrl_IsCalledBy ilNotificationsSettingsGUI: ilPersonalSettingsGUI
 */
class ilNotificationsSettingsGUI
{
    protected Container $dic;

    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
    }

    public function executeCommand(): void
    {
        $this->dic->ui()->mainTemplate()->addJavaScript('Services/Notifications/js/service-worker-loader.js');
        $this->dic->ui()->mainTemplate()->addCSS('Services/Notifications/templates/default/push.css');

        switch ($this->dic->ctrl()->getCmd()) {
            case 'checkSubscription':
                $this->checkSubscription();
                break;
            case 'addSubscription':
                $this->addSubscription();
                break;
            case 'removeSubscription':
                $this->removeSubscription();
                break;
            case 'saveSettings':
                $this->saveSettings();
            default:
                $this->showSettings();
        }
    }

    public function checkSubscription(): void
    {
        $auth = $this->dic->http()->wrapper()->post()->retrieve('auth', $this->dic->refinery()->to()->string());
        foreach ((new PushRepository())->getUserSubscriptions($this->dic->user()->getId()) as $subscription) {
            if ($auth === $subscription->getAuth()) {
                echo '';
                exit;
            }
        }

        echo $this->dic->language()->txt('push_client_already_used');
        exit;
    }

    public function addSubscription(): void
    {
        $data = json_decode(
            $this->dic->http()->wrapper()->post()->retrieve('subscription', $this->dic->refinery()->to()->string()),
            true
        );
        (new PushRepository())->addSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
        );
    }

    public function removeSubscription(): void
    {
        (new PushRepository())->deleteSubscription(
            $this->dic->http()->wrapper()->post()->retrieve('auth', $this->dic->refinery()->to()->string())
        );
    }

    public function showSettings(?Form $form = null): void
    {
        $check_url = $this->dic->ctrl()->getLinkTargetByClass(self::class, 'checkSubscription');
        $this->dic->ui()->mainTemplate()->addOnLoadCode("il.Notifications.checkSubscription('$check_url')");
        $this->dic->ui()->mainTemplate()->setOnScreenMessage(
            $this->dic->ui()->mainTemplate()::MESSAGE_TYPE_QUESTION,
            $this->dic->language()->txt('push_client_inactive')
        );

        $public_key = $this->dic->clientIni()->readVariable('push', 'public');
        $target = $this->dic->ctrl()->getLinkTargetByClass(self::class, 'addSubscription');
        $sub = $this->dic->ui()->factory()->button()->standard($this->dic->language()->txt('activate'), '')->withOnLoadCode(
            static fn($id) => "il.Notifications.initSub($id, '$public_key', '$target');"
        );
        $target = $this->dic->ctrl()->getLinkTargetByClass(self::class, 'removeSubscription');
        $unsub = $this->dic->ui()->factory()->button()->standard($this->dic->language()->txt('deactivate'), '')->withOnLoadCode(
            static fn($id) => "il.Notifications.initUnsub($id, '$target');"
        );

        if ($form === null) {
            $values = [];
            foreach ((new PushProviderObjective())->getArtifacts() as $provider) {
                $values[$provider->getId()] = $this->dic->user()->getPref('push_' . $provider->getId()) === '1';
            }
            $form = $this->getForm($values);
        }

        $this->dic->ui()->mainTemplate()->setContent($this->dic->ui()->renderer()->render([$sub, $unsub, $form]));
        $this->dic->ui()->mainTemplate()->printToStdout();
    }

    public function saveSettings(): void
    {
        $form = $this->getForm()->withRequest($this->dic->http()->request());
        $data = $form->getData();
        if (isset($data['push'])) {
            foreach ($data['push'] as $key => $value) {
                $this->dic->user()->setPref('push_' . $key, $value ? '1' : '0');
            }
            $this->dic->user()->update();
        }
    }

    protected function getForm(?array $values = null): Form
    {
        $provider = [];
        foreach ((new PushProviderObjective())->getArtifacts() as $key => $value) {
            $provider[$value->getId()] = $this->dic->ui()->factory()->input()->field()->checkbox(
                $value->getName(),
                $value->getDescription(),
            )->withValue($values[$value->getId()] ?? false);
        }

        return $this->dic->ui()->factory()->input()->container()->form()->standard(
            $this->dic->ctrl()->getFormAction($this, 'saveSettings'),
            [
                'push' => $this->dic->ui()->factory()->input()->field()->section(
                    $provider,
                    $this->dic->language()->txt('push_settings')
                )
            ]
        );
    }
}
