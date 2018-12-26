<?php

declare(strict_types=1);

namespace App\Service;

use App\Abstracts\ServiceAbstracts;
use App\Constant\ErrorConstant;
use App\Exception\ApiException;
use App\Exception\ValidationException;
use App\Helper\HtmlHelper;
use App\Helper\MarkdownHelper;
use App\Helper\ValidatorHelper;
use App\Traits\CommentableTraits;
use App\Traits\FollowableTraits;
use App\Traits\BaseTraits;
use App\Traits\VotableTraits;

/**
 * 文章
 *
 * @property-read \App\Model\ArticleModel      currentModel
 *
 * Class ArticleService
 * @package App\Service
 */
class ArticleService extends ServiceAbstracts
{
    use BaseTraits, CommentableTraits, FollowableTraits, VotableTraits;

    /**
     * 获取隐私字段
     *
     * @return array
     */
    public function getPrivacyFields(): array
    {
        return ['delete_time'];
    }

    /**
     * 获取允许排序的字段
     *
     * @return array
     */
    public function getAllowOrderFields(): array
    {
        return ['vote_count', 'create_time', 'update_time'];
    }

    /**
     * 获取允许搜索的字段
     *
     * @return array
     */
    public function getAllowFilterFields(): array
    {
        return ['article_id', 'user_id'];
    }

    /**
     * 获取文章列表
     *
     * @param  array $condition
     * 两个参数中仅可指定一个
     * [
     *     'user_id'    => '',
     *     'topic_id'   => '', // todo: 支持该参数
     *     'is_deleted' => true, // 该值为 true 时，获取已删除的记录；否则获取未删除的记录
     * ]
     * @param  bool  $withRelationship
     * @return array
     */
    public function getList(array $condition = [], bool $withRelationship = false): array
    {
        $where = $this->getWhere();
        $defaultOrder = ['create_time' => 'DESC'];

        if (isset($condition['user_id'])) {
            $this->userService->hasOrFail($condition['user_id']);
            $where = ['user_id' => $condition['user_id']];
        }

        elseif (isset($condition['is_deleted']) && $condition['is_deleted']) {
            $this->articleModel->onlyTrashed();
            $defaultOrder = ['delete_time' => 'DESC'];
        }

        $list = $this->articleModel
            ->where($where)
            ->order($this->getOrder($defaultOrder))
            ->field($this->getPrivacyFields(), true)
            ->paginate();

        $list['data'] = $this->handle($list['data']);

        if ($withRelationship) {
            $list['data'] = $this->addRelationship($list['data']);
        }

        return $list;
    }

    /**
     * 发表文章
     *
     * @param  int    $userId          用户ID
     * @param  string $title           文章标题
     * @param  string $contentMarkdown Markdown 正文
     * @param  string $contentRendered HTML 正文
     * @param  array  $topicIds        话题ID数组
     * @return int
     */
    public function create(
        int    $userId,
        string $title,
        string $contentMarkdown,
        string $contentRendered,
        array  $topicIds = null
    ): int {
        [
            $title,
            $contentMarkdown,
            $contentRendered,
            $topicIds,
        ] = $this->createValidator(
            $title,
            $contentMarkdown,
            $contentRendered,
            $topicIds
        );

        // 添加文章
        $articleId = (int)$this->articleModel->insert([
            'user_id'          => $userId,
            'title'            => $title,
            'content_markdown' => $contentMarkdown,
            'content_rendered' => $contentRendered,
        ]);

        // 添加话题关系
        $topicable = [];
        foreach ($topicIds as $topicId) {
            $topicable[] = [
                'topic_id'       => $topicId,
                'topicable_id'   => $articleId,
                'topicable_type' => 'article'
            ];
        }
        if ($topicable) {
            $this->topicableModel->insert($topicable);
        }

        // 自动关注该文章
        $this->articleService->addFollow($userId, $articleId);

        // 用户的 article_count + 1
        $this->userModel
            ->where(['user_id' => $userId])
            ->update(['article_count[+]' => 1]);

        return $articleId;
    }

    /**
     * 发表文章前对参数进行验证
     *
     * @param  string $title
     * @param  string $contentMarkdown
     * @param  string $contentRendered
     * @param  array  $topicIds
     * @return array
     */
    private function createValidator(
        string $title,
        string $contentMarkdown,
        string $contentRendered,
        array $topicIds = null
    ): array {
        $errors = [];

        // 验证标题
        if (!$title) {
            $errors['title'] = '标题不能为空';
        } elseif (!ValidatorHelper::isMin($title, 2)) {
            $errors['title'] = '标题长度不能小于 2 个字符';
        } elseif (!ValidatorHelper::isMax($title, 80)) {
            $errors['title'] = '标题长度不能超过 80 个字符';
        }

        // 验证正文不能为空
        $contentMarkdown = HtmlHelper::removeXss($contentMarkdown);
        $contentRendered = HtmlHelper::removeXss($contentRendered);

        // content_markdown 和 content_rendered 至少需传入一个；都传入时，以 content_markdown 为准
        if (!$contentMarkdown && !$contentRendered) {
            $errors['content_markdown'] = $errors['content_rendered'] = '正文不能为空';
        } elseif (!$contentMarkdown) {
            $contentMarkdown = HtmlHelper::toMarkdown($contentRendered);
        } else {
            $contentRendered = MarkdownHelper::toHtml($contentMarkdown);
        }

        // 验证正文长度
        if (
               !isset($errors['content_markdown'])
            && !isset($errors['content_rendered'])
            && !ValidatorHelper::isMax(strip_tags($contentRendered), 100000)
        ) {
            $errors['content_markdown'] = $errors['content_rendered'] = '正文不能超过 100000 个字';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        if (is_null($topicIds)) {
            $topicIds = [];
        }

        // 过滤不存在的 topic_id
        $isTopicIdsExist = $this->topicService->hasMultiple($topicIds);
        $topicIds = [];
        foreach ($isTopicIdsExist as $topicId => $isExist) {
            if ($isExist) {
                $topicIds[] = $topicId;
            }
        }

        return [$title, $contentMarkdown, $contentRendered, $topicIds];
    }

    /**
     * 更新文章
     *
     * @param int    $articleId
     * @param string $title
     * @param string $contentMarkdown
     * @param string $contentRendered
     * @param array  $topicIds         为 null 时表示不更新 topic_id
     */
    public function update(
        int    $articleId,
        string $title = null,
        string $contentMarkdown = null,
        string $contentRendered = null,
        array  $topicIds = null
    ): void {
        [
            $data,
            $topicIds
        ] = $this->updateValidator(
            $articleId,
            $title,
            $contentMarkdown,
            $contentRendered,
            $topicIds
        );

        // 更新文章信息
        if ($data) {
            $this->articleModel
                ->where(['article_id' => $articleId])
                ->update($data);
        }

        // 更新话题关系
        if (!is_null($topicIds)) {
            $this->topicableModel
                ->where([
                    'topicable_id'   => $articleId,
                    'topicable_type' => 'article',
                ])
                ->delete();

            $topicable = [];
            foreach ($topicIds as $topicId) {
                $topicable[] = [
                    'topic_id'       => $topicId,
                    'topicable_id'   => $articleId,
                    'topicable_type' => 'article',
                ];
            }

            if ($topicable) {
                $this->topicableModel->insert($topicable);
            }
        }
    }

    /**
     * 更新文章前的字段验证
     *
     * @param  int    $articleId
     * @param  string $title
     * @param  string $contentMarkdown
     * @param  string $contentRendered
     * @param  array  $topicIds
     * @return array                   经过处理后的数据
     */
    private function updateValidator(
        int    $articleId,
        string $title = null,
        string $contentMarkdown = null,
        string $contentRendered = null,
        array  $topicIds = null
    ): array {
        $data = [];

        $userId = $this->roleService->userIdOrFail();
        $articleInfo = $this->articleModel->get($articleId);

        if (!$articleInfo) {
            throw new ApiException(ErrorConstant::ARTICLE_NOT_FOUND);
        }

        if ($articleInfo['user_id'] != $userId && !$this->roleService->managerId()) {
            throw new ApiException(ErrorConstant::ARTICLE_ONLY_AUTHOR_CAN_EDIT);
        }

        $errors = [];

        // 验证标题
        if (!is_null($title)) {
            if (!ValidatorHelper::isMin($title, 2)) {
                $errors['title'] = '标题长度不能小于 2 个字符';
            } elseif (!ValidatorHelper::isMax($title, 80)) {
                $errors['title'] = '标题长度不能超过 80 个字符';
            } else {
                $data['title'] = $title;
            }
        }

        // 验证正文
        if (!is_null($contentMarkdown) || !is_null($contentRendered)) {
            if (!is_null($contentMarkdown)) {
                $contentMarkdown = HtmlHelper::removeXss($contentMarkdown);
            }

            if (!is_null($contentRendered)) {
                $contentRendered = HtmlHelper::removeXss($contentRendered);
            }

            if (!$contentMarkdown && !$contentRendered) {
                $errors['content_markdown'] = $errors['content_rendered'] = '正文不能为空';
            } elseif (!$contentMarkdown) {
                $contentMarkdown = HtmlHelper::toMarkdown($contentRendered);
            } else {
                $contentRendered = MarkdownHelper::toHtml($contentMarkdown);
            }

            // 验证正文长度
            if (
                   !isset($errors['content_markdown'])
                && !isset($errors['content_rendered'])
                && !ValidatorHelper::isMax(strip_tags($contentRendered), 100000)
            ) {
                $errors['content_markdown'] = $errors['content_rendered'] = '正文不能超过 100000 个字';
            }

            if (!isset($errors['content_markdown']) && !isset($errors['content_rendered'])) {
                $data['content_markdown'] = $contentMarkdown;
                $data['content_rendered'] = $contentRendered;
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        if (!is_null($topicIds)) {
            $isTopicIdsExist = $this->topicService->hasMultiple($topicIds);
            $topicIds = [];
            foreach ($isTopicIdsExist as $topicId => $isExist) {
                if ($isExist) {
                    $topicIds[] = $topicId;
                }
            }
        }

        return [$data, $topicIds];
    }

    /**
     * 删除文章
     *
     * @param  int  $articleId
     */
    public function delete(int $articleId): void
    {
        $userId = $this->roleService->userIdOrFail();
        $articleInfo = $this->articleModel->field('user_id')->get($articleId);

        if (!$articleInfo) {
            return;
        }

        if ($articleInfo['user_id'] != $userId && !$this->roleService->managerId()) {
            throw new ApiException(ErrorConstant::ARTICLE_ONLY_AUTHOR_CAN_DELETE);
        }

        $this->articleModel->delete($articleId);

        // 该文章的作者的 article_count - 1
        $this->userModel
            ->where(['user_id' => $articleInfo['user_id']])
            ->update(['article_count[-]' => 1]);

        // 关注该文章的用户的 following_article_id - 1
        $followerIds = $this->followModel
            ->where(['followable_id' => $articleId, 'followable_type' => 'article'])
            ->pluck('user_id');
        $this->userModel
            ->where(['user_id' => $followerIds])
            ->update(['following_article_count[-]' => 1]);
    }

    /**
     * 批量软删除文章
     *
     * @param array $articleIds
     */
    public function deleteMultiple(array $articleIds): void
    {
        if (!$articleIds) {
            return;
        }

        $articles = $this->articleModel
            ->field(['article_id', 'user_id'])
            ->select($articleIds);

        if (!$articles) {
            return;
        }

        $articleIds = array_column($articles, 'article_id');
        $this->articleModel->delete($articleIds);

        $users = [];

        // 这些文章的作者的 article_count - 1
        foreach ($articles as $article) {
            isset($users[$article['user_id']]['article_count'])
                ? $users[$article['user_id']]['article_count'] += 1
                : $users[$article['user_id']]['article_count'] = 1;
        }

        // 关注这些文章的用户的 following_article_count - 1
        $followerIds = $this->followModel
            ->where(['followable_id' => $articleIds, 'followable_type' => 'article'])
            ->pluck('user_id');

        foreach ($followerIds as $followerId) {
            isset($users[$followerId]['following_article_count'])
                ? $users[$followerId]['following_article_count'] += 1
                : $users[$followerId]['following_article_count'] = 1;
        }

        foreach ($users as $userId => $user) {
            $update = [];

            if (isset($user['article_count'])) {
                $update['article_count[-]'] = $user['article_count'];
            }

            if (isset($user['following_article_count'])) {
                $update['following_article_count[-]'] = $user['following_article_count'];
            }

            $this->userModel
                ->where(['user_id' => $userId])
                ->update($update);
        }
    }

    /**
     * 恢复文章
     *
     * @param int $articleId
     */
    public function restore(int $articleId): void
    {

    }

    /**
     * 批量恢复文章
     *
     * @param array $articleIds
     */
    public function restoreMultiple(array $articleIds): void
    {

    }

    /**
     * 硬删除文章
     *
     * @param int $articleId
     */
    public function destroy(int $articleId): void
    {

    }

    /**
     * 批量硬删除文章
     *
     * @param array $articleIds
     */
    public function destroyMultiple(array $articleIds): void
    {

    }

    /**
     * 对数据库中取出的文章信息进行处理
     * todo 处理文章
     *
     * @param  array $articles 文章信息，或多个文章组成的数组
     * @return array
     */
    public function handle(array $articles): array
    {
        if (!$articles) {
            return $articles;
        }

        if (!$isArray = is_array(current($articles))) {
            $articles = [$articles];
        }

        foreach ($articles as &$article) {
        }

        if ($isArray) {
            return $articles;
        }

        return $articles[0];
    }

    /**
     * 获取在 relationship 中使用的 article
     *
     * @param  array $articleIds
     * @return array
     */
    public function getInRelationship(array $articleIds): array
    {
        $articles = array_combine($articleIds, array_fill(0, count($articleIds), []));

        $articlesTmp = $this->articleModel
            ->field(['article_id', 'title', 'create_time', 'update_time'])
            ->select($articleIds);

        foreach ($articlesTmp as $item) {
            $articles[$item['article_id']] = $item;
        }

        return $articles;
    }

    /**
     * 为文章添加 relationship 字段
     * {
     *     user: {}
     *     topics: [ {}, {}, {} ]
     *     is_following: false
     *     voting: up、down、''
     * }
     *
     * @param  array $articles
     * @param  array $relationship ['is_following': bool]
     * @return array
     */
    public function addRelationship(array $articles, array $relationship = []): array
    {
        if (!$articles) {
            return $articles;
        }

        if (!$isArray = is_array(current($articles))) {
            $articles = [$articles];
        }

        $articleIds = array_unique(array_column($articles, 'article_id'));
        $userIds = array_unique(array_column($articles, 'user_id'));

        // is_following
        if (isset($relationship['is_following'])) {
            $followingArticleIds = $relationship['is_following'] ? $articleIds : [];
        } else {
            $followingArticleIds = $this->followService->getIsFollowingInRelationship($articleIds, 'article');
        }

        $votings = $this->voteService->getVotingInRelationship($articleIds, 'article');
        $users = $this->userService->getInRelationship($userIds);
        $topics = $this->topicService->getInRelationship($articleIds, 'article');

        // 合并数据
        foreach ($articles as &$article) {
            $article['relationship'] = [
                'user'         => $users[$article['user_id']],
                'topics'       => $topics[$article['article_id']],
                'is_following' => in_array($article['article_id'], $followingArticleIds),
                'voting'       => $votings[$article['article_id']],
            ];
        }

        if ($isArray) {
            return $articles;
        }

        return $articles[0];
    }
}
