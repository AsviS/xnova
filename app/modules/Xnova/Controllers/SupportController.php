<?php

namespace Xnova\Controllers;

/**
 * @author AlexPro
 * @copyright 2008 - 2018 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Xnova\Exceptions\ErrorException;
use Xnova\Exceptions\RedirectException;
use Xnova\Exceptions\SuccessException;
use Xnova\Helpers;
use Xnova\Models\Support;
use Xnova\Request;
use Xnova\User;
use Xnova\Sms;
use Xnova\Controller;

/**
 * @RoutePrefix("/support")
 * @Route("/")
 * @Route("/{action}/")
 * @Route("/{action}{params:(/.*)*}")
 * @Private
 */
class SupportController extends Controller
{
	public function initialize ()
	{
		parent::initialize();

		$this->tag->setTitle('Техподдержка');
		$this->showTopPanel(false);
	}

	public function addAction ()
	{
		$text = $this->request->getPost('text', 'string', '');
		$subject = $this->request->getPost('subject', 'string', '');

		if (empty($text) || empty($subject))
			throw new ErrorException('Не заполнены все поля');

		$ticket = new Support();

		$ticket->user_id = $this->user->id;
		$ticket->subject = Helpers::checkString($subject);
		$ticket->text = Helpers::checkString($text);
		$ticket->status = 1;

		if (!$ticket->create())
			throw new \Exception('Не удалось создать тикет');

		$sms = new Sms();
		$sms->send($this->config->sms->login, 'Создан новый тикет №' . $ticket->id . ' ('.$this->user->username.')');

		throw new SuccessException('Задача добавлена');
	}

	public function answerAction ($id)
	{
		$id = (int) $id;

		if (!$id)
			throw new RedirectException('Не задан ID тикета', '/support/');

		$text = $this->request->getPost('text', 'string', '');

		if (empty($text))
			throw new ErrorException('Не заполнены все поля');

		$ticket = Support::findFirst($id);

		if (!$ticket)
			throw new RedirectException('Тикет не найден', '/support/');

		$text = $ticket->text . '<hr>' . $this->user->username . ' ответил в ' . date("d.m.Y H:i:s", time()) . ':<br>' . Helpers::checkString($text) . '';

		$ticket->text = $text;
		$ticket->status = 3;
		$ticket->update();

		User::sendMessage(1, false, time(), 4, $this->user->username, 'Поступил ответ на тикет №' . $id);

		if ($ticket->status == 2)
		{
			$sms = new Sms();
			$sms->send($this->config->sms->login, 'Поступил ответ на тикет №' . $ticket->id . ' ('.$this->user->username.')');
		}

		throw new SuccessException('Задача обновлена');
	}
	
	public function indexAction ()
	{
		$list = [];

		$tickets = Support::find([
			'conditions' => 'user_id = ?0',
			'bind' => [$this->user->id],
			'order' => 'time DESC'
		]);

		foreach ($tickets as $ticket)
		{
			$list[] = [
				'id' => (int) $ticket->id,
				'status' => (int) $ticket->status,
				'subject' => $ticket->subject,
				'date' => $this->game->datezone("d.m.Y H:i:s", $ticket->time)
			];
		}

		Request::addData('page', [
			'items' => $list
		]);
	}

	public function infoAction ($id)
	{
		$ticket = Support::findFirst([
			'conditions' => 'user_id = :user: AND id = :id:',
			'bind' => [
				'user' => $this->user->id,
				'id' => $id
			],
		]);

		if (!$ticket)
			throw new \Exception('Тикет не найден');

		Request::setData([
			'id' => (int) $ticket->id,
			'status' => (int) $ticket->status,
			'subject' => $ticket->subject,
			'date' => $this->game->datezone("d.m.Y H:i:s", $ticket->time),
			'text' => html_entity_decode($ticket->text, ENT_NOQUOTES, "CP1251"),
		]);

		$this->view->disable();
	}
}