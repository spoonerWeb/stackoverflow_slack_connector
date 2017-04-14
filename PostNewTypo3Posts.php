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
    protected $stackAppsKey = 'iONJDdLasyJq99jZ24Ewfw((';

    /**
     * @var string
     */
    protected $slackUrl = 'https://hooks.slack.com/services/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';

    /**
     * @var string
     */
    protected $apiTagUrl = 'https://api.stackexchange.com/2.2/questions?site=stackoverflow&filter=withbody&order=asc';

    /**
     * @var string
     */
    protected $fileWithTimestampOfLastExecution = 'last_execution.txt';

    /**
     * @param array $data
     * @return array
     */
    public function convertQuestionToSlackData(array $data)
    {
        $postData = [];
        foreach ($data['items'] as $question) {
            $postData[$question['question_id']] = [
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
                                'title' => 'Created',
                                'value' => '<!date^' . $question['creation_date'] . '^{date} at {time}|' . strftime('%A.%d.%Y',
                                        $question['creation_date']) . '>',
                                'short' => true
                            ],
                            [
                                'title' => 'Author',
                                'value' => $question['owner']['display_name'],
                                'short' => true
                            ],
                            [
                                'title' => 'Tags',
                                'value' => implode(', ', $question['tags'])
                            ]
                        ]
                    ]
                ]
            ];
        }

        return $postData;
    }

    /**
     * @param array $data
     * @return void
     */
    public function sendPostToSlack(array $data)
    {
        foreach ($data as $post) {
            $curlHandler = curl_init();
            curl_setopt($curlHandler, CURLOPT_URL, $this->slackUrl);
            curl_setopt($curlHandler, CURLOPT_POST, count($post));
            curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode($post));

            curl_exec($curlHandler);

            curl_close($curlHandler);
        }
    }

    /**
     * @param string $tag
     * @return array|null
     */
    public function getNewestPostsInStackOverflow($tag = 'typo3')
    {
        $lastExecution = file_get_contents($this->fileWithTimestampOfLastExecution) ?: 0;
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
$newestQuestions = $newPostService->getNewestPostsInStackOverflow();
$postData = $newPostService->convertQuestionToSlackData($newestQuestions);
$newPostService->sendPostToSlack($postData);

$newPostService->setNewTimestamp();
