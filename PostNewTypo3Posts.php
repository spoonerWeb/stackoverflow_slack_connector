<?php
/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

class PostNewTypo3Posts
{

    /**
     * @var string
     */
    protected $stackAppsKey = '';

    /**
     * WebHook URL to channel #stackoverflow
     *
     * @var string
     */
    protected $slackUrl = '';

    /**
     * @var string
     */
    protected $apiTagUrl = 'https://api.stackexchange.com/2.2/questions?site=stackoverflow&filter=withbody&order=asc';

    /**
     * @var string
     */
    protected $fileWithTimestampOfLastExecution = 'last_execution.txt';

    /**
     * @var string
     */
    protected $fileWithStackAppsKey = 'key.txt';

    /**
     * @var string
     */
    protected $fileWithConfigurationOfWebHookUrls = 'webhooks.ini';

    /**
     * @var array
     */
    protected $webhooks = [];

    /**
     * @return void
     */
    public function setStackAppsKey()
    {
        $this->stackAppsKey = str_replace("\n", '', file_get_contents($this->fileWithStackAppsKey));
    }

    /**
     * @return void
     */
    public function setWebHookUrls()
    {
        $this->webhooks = parse_ini_file($this->fileWithConfigurationOfWebHookUrls);
    }

    /**
     * @param array $data
     * @return array
     */
    public function convertQuestionToSlackData(array $data)
    {
        $postData = [];
        foreach ($data['items'] as $question) {
            $attachment = [
                'attachments' => [
                    [
                        'fallback' => 'New question in StackOverflow: ' . $question['title'],
                        'title' => $question['title'],
                        'title_link' => $question['link'],
                        'thumb_url' => $question['owner']['profile_image'],
                        'text' => str_replace(
                            ['&', '<p>', '</p>', '<', '>'],
                            ['&amp;', '', '', '&lt;', '&gt;'],
                            $question['body']
                        ),
                        'fields' => [
                            [
                                'title' => 'Tags',
                                'value' => implode(', ', $question['tags'])
                            ]
                        ]
                    ]
                ]
            ];
            foreach ($question['tags'] as $tag) {
                if (array_key_exists($tag, $this->webhooks)) {
                    $postData[$tag][$question['question_id']] = $attachment;
                }
            }
        }

        return $postData;
    }

    /**
     * @param array $data
     * @return void
     */
    public function sendPostToSlack(array $data)
    {
        foreach ($data as $tag => $postData) {
            foreach ($postData as $post) {
                $curlHandler = curl_init();
                curl_setopt($curlHandler, CURLOPT_URL, $this->webhooks[$tag]);
                curl_setopt($curlHandler, CURLOPT_POST, count($post));
                curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode($post));

                curl_exec($curlHandler);

                curl_close($curlHandler);
            }
        }
    }

    /**
     * @param string $tag
     * @return array|null
     */
    public function getNewestPostsInStackOverflow($tag = 'typo3')
    {
        $lastExecution = (int)file_get_contents($this->fileWithTimestampOfLastExecution) ?: 0;
        $taggedQuestionsUrl = $this->apiTagUrl . '&tagged=' . $tag . '&key=' . $this->stackAppsKey . '&fromdate=' . $lastExecution;
        $questions = file_get_contents('compress.zlib://' . $taggedQuestionsUrl);

        return json_decode($questions, true);
    }

    /**
     * @return void
     */
    public function setNewTimestamp()
    {
        file_put_contents($this->fileWithTimestampOfLastExecution, time());
    }

}

$newPostService = new PostNewTypo3Posts();
$newPostService->setStackAppsKey();
$newPostService->setWebHookUrls();
$newestQuestions = $newPostService->getNewestPostsInStackOverflow();
$postData = $newPostService->convertQuestionToSlackData($newestQuestions);
$newPostService->sendPostToSlack($postData);

if (!empty($postData)) {
    $newPostService->setNewTimestamp();
}
