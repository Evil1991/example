<?php

class SubscribeController extends BaseController
{
    /***
     * Актиаировать почту и подписки на этой почте
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function postSubscribeCode(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $data = $body['data'];

        $confirm_code = $data['code'];
        $email = $data['email'];
        $arRubric = $data['subscriptions'];

        $subscribe = new Subscribe;

        $subscriber = $subscribe->getSubscriber($email, $confirm_code);

        if(!$subscribe->confirmSubscribe($subscriber['ID']))
        {
            throw new LogicException([
                'code'      => 'subscribe_confirm_fail',
                'message'   => 'Не удалось подтвердить подписку'
            ]);
        }
        $subscribe->updateSubscribeRubric($subscriber['ID'], $arRubric);

        return ResponseBuilder::successJSON($response, ['result' => 'ok']);
    }

    public function postSubscribeEmail(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        $rubricIds = [];
        $email = $body['email'];
        foreach($body['list'] as $rubric){
            $rubricIds[] = $rubric['id'];
        }

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseErrorsService::setError(
                AuthErrorsEnum::INVALID_EMAIL['code'],
                AuthErrorsEnum::INVALID_EMAIL['message']
            );
        }

        if(count($rubricIds) < 1) {
            ResponseErrorsService::setError(
                SubscribeErrorsEnum::EMPTY_RUBRIC_IDS['code'],
                SubscribeErrorsEnum::EMPTY_RUBRIC_IDS['message']
            );
        }

        if (ResponseErrorsService::hasErrors()) {
            return ResponseBuilder::errorJSON($response);
        }

        $subscribe = new Subscribe;
        $result = $subscribe->addSubscribe($rubricIds, $body['email']);

        if ($result > 0){
            return ResponseBuilder::successJSON($response, $result);
        }

        return ResponseBuilder::successJSON($response, $_REQUEST);
    }
}



?>