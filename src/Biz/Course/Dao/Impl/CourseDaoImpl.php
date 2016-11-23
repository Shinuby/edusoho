<?php

namespace Biz\Course\Dao\Impl;

use Biz\Course\Dao\CourseDao;
use Codeages\Biz\Framework\Dao\GeneralDaoImpl;

class CourseDaoImpl extends GeneralDaoImpl implements CourseDao
{
    protected $table = 'course';

    public function get($id, $lock = false)
    {
    	$course = parent::get($id, $lock);
    	return CourseSerialize::unserialize($course);
    }

    public function declares()
    {

    }
}

class CourseSerialize
{
    public static function serialize(array &$course)
    {
        if (isset($course['tags'])) {
            if (is_array($course['tags']) && !empty($course['tags'])) {
                $course['tags'] = '|'.implode('|', $course['tags']).'|';
            } else {
                $course['tags'] = '';
            }
        }

        if (isset($course['goals'])) {
            if (is_array($course['goals']) && !empty($course['goals'])) {
                $course['goals'] = '|'.implode('|', $course['goals']).'|';
            } else {
                $course['goals'] = '';
            }
        }

        if (isset($course['audiences'])) {
            if (is_array($course['audiences']) && !empty($course['audiences'])) {
                $course['audiences'] = '|'.implode('|', $course['audiences']).'|';
            } else {
                $course['audiences'] = '';
            }
        }

        if (isset($course['teacherIds'])) {
            if (is_array($course['teacherIds']) && !empty($course['teacherIds'])) {
                $course['teacherIds'] = '|'.implode('|', $course['teacherIds']).'|';
            } else {
                $course['teacherIds'] = null;
            }
        }

        return $course;
    }

    public static function unserialize(array $course = null)
    {
        if (empty($course)) {
            return $course;
        }

        $course['tags'] = empty($course['tags']) ? array() : explode('|', trim($course['tags'], '|'));

        if (empty($course['goals'])) {
            $course['goals'] = array();
        } else {
            $course['goals'] = explode('|', trim($course['goals'], '|'));
        }

        if (empty($course['audiences'])) {
            $course['audiences'] = array();
        } else {
            $course['audiences'] = explode('|', trim($course['audiences'], '|'));
        }

        if (empty($course['teacherIds'])) {
            $course['teacherIds'] = array();
        } else {
            $course['teacherIds'] = explode('|', trim($course['teacherIds'], '|'));
        }

        return $course;
    }

    public static function unserializes(array $courses)
    {
        return array_map(function ($course) {
            return CourseSerialize::unserialize($course);
        }, $courses);
    }
}