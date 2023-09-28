<?php

/**
 * @author Marca Alessandro <alessandro.marca@unimi.it>
 * @copyright Copyright (c) 2023, Marca Alessandro <alessandro.marca@unimi.it>
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace OCA\Files_external_onedrive\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * Oauth controller for OneDrive.
 */
class OauthController extends Controller {
	public const URL_AUTHORIZE = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
	public const URL_ACCESS_TOKEN = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
	public const SCOPES = 'Files.Read Files.Read.All Files.ReadWrite Files.ReadWrite.All User.Read Sites.ReadWrite.All offline_access';

	/**
	 * L10N service.
	 *
	 * @var IL10N
	 */
	protected $l10n;

	protected $proxy;

	/**
	 * Creates a new storages controller.
	 *
	 * @param string   $AppName application name
	 * @param IRequest $request request
	 * @param IL10N    $l10n    l10n service
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		IL10N $l10n
	) {
		parent::__construct($AppName, $request);
		$this->l10n = $l10n;
		$config = new \OC\Config('config/');
		$this->proxy = $config->getValue('proxy');
	}

	/**
	 * Create a storage from its parameters.
	 *
	 * @param string $client_id
	 * @param string $client_secret
	 * @param string $redirect
	 * @param int    $step
	 * @param string $code
	 *
	 * @return DataResponse
	 *
	 * @NoAdminRequired
	 */
	public function receiveToken(
		$client_id,
		$client_secret,
		$redirect,
		$step,
		$code
	) {
		$clientId = $client_id;
		$clientSecret = $client_secret;

		if (null !== $clientId && null !== $clientSecret && null !== $redirect) {
			$provider = new \League\OAuth2\Client\Provider\GenericProvider([
				'clientId' => $clientId,
				'clientSecret' => $clientSecret,
				'redirectUri' => $redirect,
				'urlAuthorize' => self::URL_AUTHORIZE,
				'urlAccessToken' => self::URL_ACCESS_TOKEN,
				'urlResourceOwnerDetails' => '',
				'proxy' => $this->proxy,
				'scopes' => self::SCOPES
			]);

			$data = [
				'client_id' => $clientId,
			];

			if (null !== $step) {
				$step = (int) $step;
				if (1 === $step) {
					try {
						$authUrl = $provider->getAuthorizationUrl();

						return new DataResponse(
							[
								'status' => 'success',
								'data' => [
									'url' => $authUrl,
								],
							]
						);
					} catch (Exception $exception) {
						return new DataResponse(
							[
								'status' => 'error',
								'data' => [
									'message' => $this->l10n->error('Step 1 failed. Exception: %s', array('extra_context' => $e->getMessage()))
								]
							],
							Http::STATUS_UNPROCESSABLE_ENTITY
						);
					}
				} elseif (2 === $step && null !== $code) {
					try {
						$token = $provider->getAccessToken('authorization_code', [
							'code' => $code,
						]);

						if (!isset($token)) {
							return new DataResponse(
								[
									'status' => 'error',
									'data' => $token,
								],
								Http::STATUS_BAD_REQUEST
							);
						}

						$token = json_encode($token);
						$token = json_decode($token, true);
						$token['code_uid'] = uniqid();

						return new DataResponse(
							[
								'status' => 'success',
								'data' => [
									'token' => base64_encode(gzdeflate(json_encode($token), 9)),
								]
							]
						);
					} catch (Exception $exception) {
						return new DataResponse(
							[
								'status' => 'error',
								'data' => [
									'message' => $this->l10n->error('Step 2 failed. Exception: %s', array('extra_context' => $e->getMessage()))
								]
							],
							Http::STATUS_UNPROCESSABLE_ENTITY
						);
					} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
						return new DataResponse(
							[
								'status' => 'error',
								'data' => [
									'message' => $this->l10n->error('Step 2 failed. Exception: %s', array('extra_context' => $e->getMessage()))
								]
							],
							Http::STATUS_UNPROCESSABLE_ENTITY
						);
					}
				}
			}
		}
		return new DataResponse(
			[
				'status' => 'error',
				'data' => [],
			],
			Http::STATUS_BAD_REQUEST
		);
	}
}
