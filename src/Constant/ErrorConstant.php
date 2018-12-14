<?php

declare(strict_types=1);

namespace App\Constant;

/**
 * 错误代码
 *
 * Class ErrorConstant
 * @package App\Constant
 */
class ErrorConstant
{
    /**
     * 系统级错误
     */
    const SYSTEM_ERROR                    = [100000, '服务器错误'];
    const SYSTEM_MAINTAIN                 = [100001, '系统维护中'];
    const SYSTEM_IP_LIMIT                 = [100002, 'IP 受限'];
    const SYSTEM_API_NOT_FOUND            = [100001, '接口不存在'];
    const SYSTEM_API_NOT_ALLOWED          = [100000, '该接口不支持此 HTTP METHOD'];
    const SYSTEM_FIELD_VERIFY_FAILED      = [100002, '字段验证失败'];
    const SYSTEM_SEND_EMAIL_FAILED        = [100003, '邮件发送失败'];
    const SYSTEM_EMAIL_VERIFY_EXPIRED     = [100004, '邮件验证码已失效'];
    const SYSTEM_IMAGE_UPLOAD_FAILED      = [100005, '图片上传失败'];
    const SYSTEM_VOTE_TYPE_ERROR          = [100006, '投票类型只能是 up、down 中的一个'];

    /**
     * 服务级错误
     */
    const USER_TOKEN_EMPTY                = [200002, 'token 不能为空'];
    const USER_TOKEN_FAILED               = [200003, 'token 已失效'];
    const USER_TOKEN_NOT_FOUND            = [200004, 'token 对于的记录不存在'];
    const USER_NEED_MANAGE_PERMISSION     = [200005, '需要管理员权限'];
    const USER_NOT_FOUND                  = [200006, '指定用户不存在'];
    const USER_TARGET_NOT_FOUND           = [200007, '目标用户不存在'];
    const USER_HAS_BEEN_DISABLED          = [200013, '该用户已被禁用'];
    const USER_PASSWORD_ERROR             = [200014, '账号或密码错误'];
    const USER_AVATAR_UPLOAD_FAILED       = [200008, '头像上传失败'];
    const USER_COVER_UPLOAD_FAILED        = [200009, '封面上传失败'];
    const USER_CANT_FOLLOW_YOURSELF       = [200012, '不能关注你自己'];

    /**
     * 提问相关错误
     */
    const QUESTION_NOT_FOUND              = [300001, '指定提问不存在'];
    const QUESTION_ONLY_AUTHOR_CAN_EDIT   = [300004, '仅提问作者可以编辑提问'];
    const QUESTION_ONLY_AUTHOR_CAN_DELETE = [300005, '仅提问作者可以删除提问'];

    /**
     * 回答相关错误
     */
    const ANSWER_NOT_FOUND                = [310001, '指定回答不存在'];
    const ANSWER_ONLY_AUTHOR_CAN_EDIT     = [310002, '仅回答的作者可以编辑回答'];
    const ANSWER_ONLY_AUTHOR_CAN_DELETE   = [310003, '仅回答的作者可以删除回答'];

    /**
     * 评论相关错误
     */
    const COMMENT_NOT_FOUND               = [320001, '指定的评论不存在'];
    const COMMENT_ONLY_AUTHOR_CAN_EDIT    = [320002, '仅评论的作者可以编辑评论'];
    const COMMENT_ONLY_AUTHOR_CAN_DELETE  = [320003, '仅评论的作者可以删除评论'];

    /**
     * 话题相关错误
     */
    const TOPIC_NOT_FOUND                 = [400001, '指定话题不存在'];
    const TOPIC_COVER_UPLOAD_FAILED       = [400002, '话题封面上传失败'];

    /**
     * 文章相关错误
     */
    const ARTICLE_NOT_FOUND               = [500001, '指定文章不存在'];
    const ARTICLE_ONLY_AUTHOR_CAN_EDIT    = [500002, '仅文章作者可以编辑文章'];
    const ARTICLE_ONLY_AUTHOR_CAN_DELETE  = [500003, '仅文章作者可以删除文章'];

    /**
     * 举报相关错误
     */
    const REPORT_NOT_FOUND                = [600001, '指定举报不存在'];
    const REPORT_ALREADY_SUBMITTED        = [600002, '不能重复举报'];

    /**
     * 图片相关错误
     */
    const IMAGE_NOT_FOUND                 = [700001, '指定图片不存在'];
}
