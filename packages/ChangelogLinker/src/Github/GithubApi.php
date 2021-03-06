<?php declare(strict_types=1);

namespace Symplify\ChangelogLinker\Github;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Nette\Utils\Strings;
use Psr\Http\Message\ResponseInterface;
use Symplify\ChangelogLinker\Exception\Github\GithubApiException;
use Symplify\ChangelogLinker\Guzzle\ResponseFormatter;
use Throwable;
use function Safe\sprintf;

final class GithubApi
{
    /**
     * @var string
     * @see https://developer.github.com/v3/pulls/#parameters
     * Note: per_page=100 is maximum value, results need to be collected with "&page=X"
     */
    private const URL_CLOSED_PULL_REQUESTS = 'https://api.github.com/repos/%s/pulls?state=closed&per_page=100';

    /**
     * @var string
     */
    private const URL_PULL_REQUEST_BY_ID = 'https://api.github.com/repos/%s/pulls/%d';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $repositoryName;

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * @var ResponseFormatter
     */
    private $responseFormatter;

    public function __construct(
        Client $client,
        string $repositoryName,
        ResponseFormatter $responseFormatter,
        ?string $githubToken
    ) {
        $this->client = $client;
        $this->repositoryName = $repositoryName;
        $this->responseFormatter = $responseFormatter;

        // Inspired by https://github.com/weierophinney/changelog_generator/blob/master/changelog_generator.php
        if ($githubToken) {
            $this->options['headers']['Authorization'] = 'token ' . $githubToken;
        }
    }

    /**
     * @return mixed[]
     */
    public function getMergedPullRequestsSinceId(int $id): array
    {
        $pullRequests = $this->getPullRequestsSinceId($id);

        $mergedPullRequests = $this->filterMergedPullRequests($pullRequests);

        // include all
        if ($id === 0) {
            return $mergedPullRequests;
        }

        return $this->filterPullRequestsNewerThanMergedAt($mergedPullRequests, $this->getMergedAtByPullRequest($id));
    }

    private function getResponseToUrl(string $url): ResponseInterface
    {
        try {
            $response = $this->client->request('GET', $url, $this->options);
        } catch (RequestException $requestException) {
            if (Strings::contains($requestException->getMessage(), 'API rate limit exceeded')) {
                throw $this->createGithubApiTokenException('Github API rate limit exceeded.', $requestException);
            }

            // un-authorized access → provide token
            if ($requestException->getCode() === 401) {
                throw $this->createGithubApiTokenException('Github API un-authorized access.', $requestException);
            }

            throw $requestException;
        }

        if ($response->getStatusCode() !== 200) {
            throw new GithubApiException(sprintf(
                'Response to GET request "%s" failed: "%s"',
                $url,
                $response->getReasonPhrase()
            ));
        }

        return $response;
    }

    /**
     * @param mixed[] $pullRequests
     * @return mixed[]
     */
    private function filterMergedPullRequests(array $pullRequests): array
    {
        return array_filter($pullRequests, function (array $pullRequest) {
            return isset($pullRequest['merged_at']) && $pullRequest['merged_at'] !== null;
        });
    }

    /**
     * @param mixed[] $pullRequests
     * @return mixed[]
     */
    private function filterPullRequestsNewerThanMergedAt(array $pullRequests, string $mergedAt): array
    {
        return array_filter($pullRequests, function (array $pullRequest) use ($mergedAt): bool {
            return $pullRequest['merged_at'] > $mergedAt;
        });
    }

    private function getMergedAtByPullRequest(int $id): string
    {
        $url = sprintf(self::URL_PULL_REQUEST_BY_ID, $this->repositoryName, $id);
        $response = $this->getResponseToUrl($url);
        $json = $this->responseFormatter->formatToJson($response);

        return $json['merged_at'];
    }

    private function createGithubApiTokenException(string $reason, Throwable $throwable): GithubApiException
    {
        $message = $reason . PHP_EOL . 'Create a token at https://github.com/settings/tokens/new with only repository scope and use it as ENV variable: "GITHUB_TOKEN=... vendor/bin/changelog-linker ..." option.';

        return new GithubApiException($message, $throwable->getCode(), $throwable);
    }

    /**
     * @return mixed[]
     */
    private function getPullRequestsSinceId(int $id): array
    {
        $maxPage = 10; // max. 1000 merge requests to dump

        $pullRequests = [];
        for ($i = 1; $i <= $maxPage; ++$i) {
            $url = sprintf(self::URL_CLOSED_PULL_REQUESTS, $this->repositoryName) . '&page=' . $i;
            $response = $this->getResponseToUrl($url);

            // already no more pages → stop
            $newPullRequests = $this->responseFormatter->formatToJson($response);
            if (! count($newPullRequests)) {
                break;
            }

            $pullRequests = array_merge($pullRequests, $newPullRequests);

            // our id was found → stop after this one
            $pullRequestIds = array_column($newPullRequests, 'number');
            if (in_array($id, $pullRequestIds, true)) {
                break;
            }
        }

        return $pullRequests;
    }
}
