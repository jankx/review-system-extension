<?php

namespace Jankx\Extensions\ReviewSystem\Blocks;

trait RendersCommentMediaTrait
{
    protected function renderCommentMedia($comment): string
    {
        if (!$comment instanceof \WP_Comment) {
            return '';
        }

        if (!class_exists('\Jankx\Extensions\CommentMedia\CommentMediaExtension')) {
            return '';
        }

        $extension = \Jankx\Extensions\CommentMedia\CommentMediaExtension::get_instance();
        if (!$extension) {
            return '';
        }

        return (string) $extension->displayMedia('', $comment);
    }
}
