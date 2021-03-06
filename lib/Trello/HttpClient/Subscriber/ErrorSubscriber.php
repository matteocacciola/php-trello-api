<?php

namespace Trello\HttpClient\Subscriber;

use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Message\Response;
use Trello\HttpClient\Message\ResponseMediator;
use Trello\Exception\ErrorException;
use Trello\Exception\RuntimeException;
use Trello\Exception\PermissionDeniedException;
use Trello\Exception\ValidationFailedException;
use Trello\Exception\ApiLimitExceedException;
use GuzzleHttp\Event\SubscriberInterface;

/**
 * Class ErrorSubscriber
 * @package Trello\HttpClient\Subscriber
 */
class ErrorSubscriber implements SubscriberInterface
{
    /**
     * @inheritDoc
     */
    public function getEvents()
    {
        return [
            'error' => ['onRequestError'] /** @see ErrorSubscriber::onRequestError() */
        ];
    }

    /**
     * @param ErrorEvent $event
     *
     * @throws ErrorException
     * @throws ValidationFailedException
     */
    public function onRequestError(ErrorEvent $event)
    {
        /** @var Response $response */
        $response = $event->getResponse();

        switch ($response->getStatusCode()) {
            case 429:
                throw new ApiLimitExceedException('Wait a second.', 429);
                break;
        }

        $content = ResponseMediator::getContent($response);
        if (is_array($content) && isset($content['message'])) {
            if (400 == $response->getStatusCode()) {
                throw new ErrorException($content['message'], 400);
            }

            if (401 == $response->getStatusCode()) {
                throw new PermissionDeniedException($content['message'], 401);
            }

            if (422 == $response->getStatusCode() && isset($content['errors'])) {
                $errors = [];
                foreach ($content['errors'] as $error) {
                    switch ($error['code']) {
                        case 'missing':
                            $errors[] = sprintf('The %s %s does not exist, for resource "%s"', $error['field'], $error['value'], $error['resource']);
                            break;

                        case 'missing_field':
                            $errors[] = sprintf('Field "%s" is missing, for resource "%s"', $error['field'], $error['resource']);
                            break;

                        case 'invalid':
                            $errors[] = sprintf('Field "%s" is invalid, for resource "%s"', $error['field'], $error['resource']);
                            break;

                        case 'already_exists':
                            $errors[] = sprintf('Field "%s" already exists, for resource "%s"', $error['field'], $error['resource']);
                            break;

                        default:
                            $errors[] = $error['message'];
                            break;

                    }
                }

                throw new ValidationFailedException('Validation Failed: ' . implode(', ', $errors), 422);
            }
        }

        throw new RuntimeException(isset($content['message']) ? $content['message'] : $content, $response->getStatusCode());
    }
}
