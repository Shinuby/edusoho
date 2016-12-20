<?php
namespace Topxia\Service\Course\Event;

use Topxia\Common\ArrayToolkit;
use Codeages\Biz\Framework\Event\Event;
use Topxia\Service\Common\ServiceKernel;
use Topxia\Service\Taxonomy\TagOwnerManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CourseMaterialEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'course.delete'                            => 'onCourseDelete',
            'course.lesson.create'                     => 'onCourseLessonCreate',
            'course.lesson.delete'                     => 'onCourseLessonDelete',
            'course.lesson.update'                     => 'onCourseLessonUpdate',
            'upload.file.delete'                       => 'onUploadFileDelete',
            'upload.file.finish'                       => 'onUploadFileFinish',
            'course.material.create'                   => 'onMaterialCreate',
            'course.material.update'                   => 'onMaterialUpdate',
            'course.material.delete'                   => 'onMaterialDelete',

            'open.course.delete'                       => 'onOpenCourseDelete',
            'open.course.lesson.create'                => 'onOpenCourseLessonCreate',
            'open.course.lesson.update'                => 'onOpenCourseLessonUpdate',
            'open.course.lesson.delete'                => 'onOpenCourseLessonDelete',

            'course.lesson.generate.video.replay'      => 'onLiveFileReplay',
            'open.course.lesson.generate.video.replay' => 'onLiveOpenFileReplay'
        );
    }

    public function onCourseDelete(Event $event)
    {
        $course = $event->getSubject();
        $this->getMaterialService()->deleteMaterialsByCourseId($course['id']);

        $tagOwnerManager = new TagOwnerManager('course', $course['id']);
        $tagOwnerManager->delete();
    }

    public function onCourseLessonCreate(Event $event)
    {
        $context  = $event->getSubject();
        $argument = $context['argument'];
        $lesson   = $context['lesson'];

        if (in_array($lesson['type'], array('testpaper', 'live', 'text')) || !$lesson['mediaId']) {
            return false;
        }

        $material = $this->getMaterialService()->searchMaterials(
            array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'fileId'   => $lesson['mediaId'],
                'source'   => 'courselesson'
            ),
            array('createdTime', 'DESC'), 0, 1
        );

        if (!$material) {
            $fields = array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'fileId'   => $lesson['mediaId'],
                'source'   => 'courselesson'
            );
            $this->getMaterialService()->uploadMaterial($fields);
        }
    }

    public function onCourseLessonDelete(Event $event)
    {
        $context  = $event->getSubject();
        $lesson   = $context['lesson'];
        $courseId = $context['courseId'];

        $materials = $this->getMaterialService()->searchMaterials(
            array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'type'     => 'course'
            ),
            array('createdTime', 'DESC'), 0, PHP_INT_MAX
        );
        if (!$materials) {
            return false;
        }

        foreach ($materials as $key => $material) {
            if ($material['fileId'] == 0 && !empty($material['link'])) {
                $this->getMaterialService()->deleteMaterial($material['courseId'], $material['id']);
            } else {
                $updateFields = array(
                    'lessonId' => 0
                );
                $this->getMaterialService()->updateMaterial($material['id'], $updateFields, array('fileId' => $material['fileId']));
            }
        }
    }

    public function onCourseLessonUpdate(Event $event)
    {
        $context      = $event->getSubject();
        $argument     = $context['argument'];
        $lesson       = $context['lesson'];
        $sourceLesson = $context['sourceLesson'];

        if (in_array($lesson['type'], array('text', 'testpaper', 'live')) ||
            ($lesson['mediaId'] == $sourceLesson['mediaId'])
        ) {
            return false;
        }

        $material = $this->getMaterialService()->searchMaterials(
            array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'source'   => 'courselesson',
                'type'     => 'course'
            ),
            array('createdTime', 'DESC'), 0, 1
        );

        if ($material) {
            if ($lesson['mediaId'] != 0 && $lesson['mediaSource'] == 'self') {
                $this->_resetExistMaterialLessonId($material[0]);

                $fields = array(
                    'courseId' => $lesson['courseId'],
                    'lessonId' => $lesson['id'],
                    'fileId'   => $lesson['mediaId'],
                    'source'   => 'courselesson',
                    'type'     => 'course'
                );
                $this->getMaterialService()->uploadMaterial($fields);
            } elseif ($lesson['mediaSource'] != 'self' && $lesson['mediaId'] == 0) {
                $this->_resetExistMaterialLessonId($material[0]);
            }
        } else {
            $fields = array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'fileId'   => $lesson['mediaId'],
                'source'   => 'courselesson',
                'type'     => 'course'
            );
            $this->getMaterialService()->uploadMaterial($fields);
        }
    }

    public function onUploadFileDelete(Event $event)
    {
        $file = $event->getSubject();

        $materials = $this->getMaterialService()->searchMaterials(
            array('fileId' => $file['id'], 'copyId' => 0),
            array('createdTime', 'DESC'), 0, PHP_INT_MAX
        );

        if (!$materials) {
            return false;
        }

        foreach ($materials as $key => $material) {
            if ($material['source'] == 'coursematerial' && $material['lessonId']) {
                $this->getMaterialService()->deleteMaterial($material['courseId'], $material['id']);
            }
        }
    }

    public function onUploadFileFinish(Event $event)
    {
        $context = $event->getSubject();
        $file    = $context['file'];

        if (in_array($file['targetType'], array('courselesson', 'coursematerial', 'opencourselesson', 'opencoursematerial'))) {
            $file['courseId'] = $file['targetId'];
            $file['fileId']   = $file['id'];
            $file['source']   = $file['targetType'];
            $file['type']     = in_array($file['targetType'], array('opencourselesson', 'opencoursematerial')) ? 'openCourse' : 'course';

            $this->getMaterialService()->uploadMaterial($file);
        }
    }

    public function onMaterialCreate(Event $event)
    {
        $context  = $event->getSubject();
        $argument = $context['argument'];
        $material = $context['material'];

        if ($material['type'] == 'openCourse') {
            return false;
        }

        $courses   = $this->getCourseService()->findCoursesByParentIdAndLocked($material['courseId'], 1);
        $courseIds = ArrayToolkit::column($courses, 'id');

        if ($courseIds) {
            $lessons            = $this->getCourseService()->findLessonsByCopyIdAndLockedCourseIds($material['lessonId'], $courseIds);
            $lessonIds          = ArrayToolkit::column($lessons, 'id');
            $argument['copyId'] = $material['id'];

            foreach ($courseIds as $key => $courseId) {
                $argument['courseId'] = $courseId;
                $argument['lessonId'] = isset($lessonIds[$key]) ? $lessonIds[$key] : 0;

                $this->getMaterialService()->uploadMaterial($argument);
            }
        }
    }

    public function onMaterialUpdate(Event $event)
    {
        $context  = $event->getSubject();
        $argument = $context['argument'];
        $material = $context['material'];

        if ($material['type'] == 'openCourse') {
            return false;
        }

        $courseIds = ArrayToolkit::column($this->getCourseService()->findCoursesByParentIdAndLocked($material['courseId'], 1), 'id');

        if ($courseIds) {
            $copyMaterials = $this->getMaterialService()->findMaterialsByCopyIdAndLockedCourseIds($material['id'], $courseIds);

            foreach ($copyMaterials as $key => $copyMaterial) {
                if ($material['lessonId']) {
                    $parentMaterial = $this->getMaterialService()->getMaterial($material['courseId'], $copyMaterial['copyId']);
                    $copyLesson     = $this->getCourseService()->findLessonsByCopyIdAndLockedCourseIds($parentMaterial['lessonId'], array($copyMaterial['courseId']));

                    $this->getMaterialService()->updateMaterial($copyMaterial['id'], array('lessonId' => $copyLesson[0]['id']), $argument);
                } else {
                    $this->getMaterialService()->updateMaterial($copyMaterial['id'], array('lessonId' => 0), $argument);
                }
            }
        }
    }

    public function onMaterialDelete(Event $event)
    {
        $material = $event->getSubject();

        if ($material['type'] == 'openCourse') {
            return false;
        }

        $courseIds = ArrayToolkit::column($this->getCourseService()->findCoursesByParentIdAndLocked($material['courseId'], 1), 'id');

        if ($courseIds) {
            $materialIds = ArrayToolkit::column($this->getMaterialService()->findMaterialsByCopyIdAndLockedCourseIds($material['id'], $courseIds), 'id');

            foreach ($materialIds as $key => $materialId) {
                $this->getMaterialService()->deleteMaterial($courseIds[$key], $materialId);
            }
        }
    }

    public function onOpenCourseDelete(Event $event)
    {
        $course = $event->getSubject();

        $this->getMaterialService()->deleteMaterialsByCourseId($course['id'], 'openCourse');
    }

    public function onOpenCourseLessonCreate(Event $event)
    {
        $context = $event->getSubject();
        $lesson  = $context['lesson'];

        if (in_array($lesson['type'], array('liveOpen')) || !$lesson['mediaId']) {
            return false;
        }

        $material = $this->getMaterialService()->searchMaterials(
            array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'fileId'   => $lesson['mediaId'],
                'source'   => 'opencourselesson',
                'type'     => 'openCourse'
            ),
            array('createdTime', 'DESC'), 0, 1
        );

        if (!$material) {
            $fields = array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'fileId'   => $lesson['mediaId'],
                'source'   => 'opencourselesson',
                'type'     => 'openCourse'
            );
            $this->getMaterialService()->uploadMaterial($fields);
        }
    }

    public function onOpenCourseLessonUpdate(Event $event)
    {
        $context      = $event->getSubject();
        $lesson       = $context['lesson'];
        $sourceLesson = $context['sourceLesson'];

        if (in_array($lesson['type'], array('liveOpen')) || ($lesson['mediaId'] == $sourceLesson['mediaId'])) {
            return false;
        }

        $material = $this->getMaterialService()->searchMaterials(
            array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'source'   => 'opencourselesson',
                'type'     => 'openCourse'
            ),
            array('createdTime', 'DESC'), 0, 1
        );

        if ($material) {
            if ($lesson['mediaId'] != 0 && $lesson['mediaSource'] == 'self') {
                $this->_resetExistMaterialLessonId($material[0]);

                $fields = array(
                    'courseId' => $lesson['courseId'],
                    'lessonId' => $lesson['id'],
                    'fileId'   => $lesson['mediaId'],
                    'source'   => 'opencourselesson',
                    'type'     => 'openCourse'
                );
                $this->getMaterialService()->uploadMaterial($fields);
            } elseif ($lesson['mediaSource'] != 'self' && $lesson['mediaId'] == 0) {
                $this->_resetExistMaterialLessonId($material[0]);
            }
        } else {
            $fields = array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'fileId'   => $lesson['mediaId'],
                'source'   => 'opencourselesson',
                'type'     => 'openCourse'
            );
            $this->getMaterialService()->uploadMaterial($fields);
        }
    }

    public function onOpenCourseLessonDelete(Event $event)
    {
        $context = $event->getSubject();
        $lesson  = $context['lesson'];

        $materials = $this->getMaterialService()->searchMaterials(
            array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'type'     => 'openCourse'
            ),
            array('createdTime', 'DESC'), 0, PHP_INT_MAX
        );
        if (!$materials) {
            return false;
        }

        foreach ($materials as $key => $material) {
            if ($material['fileId'] == 0 && !empty($material['link'])) {
                $this->getMaterialService()->deleteMaterial($material['courseId'], $material['id']);
            } else {
                $updateFields = array(
                    'lessonId' => 0
                );
                $this->getMaterialService()->updateMaterial($material['id'], $updateFields, array('fileId' => $material['fileId']));
            }
        }
    }

    public function onLiveFileReplay(Event $event)
    {
        $context = $event->getSubject();
        $lesson  = $context['lesson'];

        if ($lesson['type'] != 'live' || ($lesson['type'] == 'live' && $lesson['replayStatus'] != 'videoGenerated')) {
            return false;
        }

        $material = $this->getMaterialService()->searchMaterials(
            array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'source'   => 'courselesson'
            ),
            array('createdTime', 'DESC'), 0, 1
        );

        if ($material) {
            $this->_resetExistMaterialLessonId($material[0]);
        }

        $fields = array(
            'courseId' => $lesson['courseId'],
            'lessonId' => $lesson['id'],
            'fileId'   => $lesson['mediaId'],
            'source'   => 'courselesson',
            'type'     => 'course'
        );
        $this->getMaterialService()->uploadMaterial($fields);
    }

    public function onLiveOpenFileReplay(Event $event)
    {
        $context = $event->getSubject();
        $lesson  = $context['lesson'];

        if ($lesson['type'] != 'liveOpen' || ($lesson['type'] == 'liveOpen' && $lesson['replayStatus'] != 'videoGenerated')) {
            return false;
        }

        $material = $this->getMaterialService()->searchMaterials(
            array(
                'courseId' => $lesson['courseId'],
                'lessonId' => $lesson['id'],
                'source'   => 'opencourselesson',
                'type'     => 'openCourse'
            ),
            array('createdTime', 'DESC'), 0, 1
        );

        if ($material) {
            $this->_resetExistMaterialLessonId($material[0]);
        }

        $fields = array(
            'courseId' => $lesson['courseId'],
            'lessonId' => $lesson['id'],
            'fileId'   => $lesson['mediaId'],
            'source'   => 'opencourselesson',
            'type'     => 'openCourse'
        );
        $this->getMaterialService()->uploadMaterial($fields);
    }

    private function _resetExistMaterialLessonId(array $material)
    {
        $updateFields = array('lessonId' => 0);

        $this->getMaterialService()->updateMaterial($material['id'],
            $updateFields, array('fileId' => $material['fileId'])
        );

        return true;
    }

    protected function getCourseService()
    {
        return ServiceKernel::instance()->createService('Course:CourseService');
    }

    protected function getUploadFileService()
    {
        return ServiceKernel::instance()->createService('File:UploadFileService');
    }

    protected function getMaterialService()
    {
        return ServiceKernel::instance()->createService('Course:MaterialService');
    }
}
