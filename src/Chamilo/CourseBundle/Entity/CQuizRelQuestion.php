<?php

namespace Chamilo\CourseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * CQuizRelQuestion
 *
 * @ORM\Table(name="c_quiz_rel_question", indexes={@ORM\Index(name="idx_cqrq_id", columns={"question_id"}), @ORM\Index(name="idx_cqrq_cidexid", columns={"c_id", "exercice_id"})})
 * @ORM\Entity
 */
class CQuizRelQuestion
{
    /**
     * @var integer
     *
     * @ORM\Column(name="iid", type="integer", precision=0, scale=0, nullable=false, unique=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $iid;

    /**
     * @var integer
     *
     * @ORM\Column(name="c_id", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $cId;

    /**
     * @var integer
     *
     * @ORM\Column(name="question_id", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $questionId;

    /**
     * @var integer
     *
     * @ORM\Column(name="exercice_id", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $exerciceId;

    /**
     * @var integer
     *
     * @ORM\Column(name="question_order", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $questionOrder;

    /**
     * @ORM\ManyToOne(targetEntity="CQuizCategory")
     * @ORM\JoinColumn(name="c_id", referencedColumnName="id")
     */
    //private $category;

    /**
     * @ORM\ManyToOne(targetEntity="CQuizQuestion")
     * @ORM\JoinColumn(name="to_group_id", referencedColumnName="iid")
     */
    //private $question;

	 /**
     * Get iid
     *
     * @return integer
     */
    public function getIid()
    {
        return $this->iid;
    }

    /**
     * Set cId
     *
     * @param integer $cId
     * @return CQuizRelQuestion
     */
    public function setCId($cId)
    {
        $this->cId = $cId;

        return $this;
    }

    /**
     * Get cId
     *
     * @return integer
     */
    public function getCId()
    {
        return $this->cId;
    }

    /**
     * Set questionId
     *
     * @param integer $questionId
     * @return CQuizRelQuestion
     */
    public function setQuestionId($questionId)
    {
        $this->questionId = $questionId;

        return $this;
    }

    /**
     * Get questionId
     *
     * @return integer
     */
    public function getQuestionId()
    {
        return $this->questionId;
    }

    /**
     * Set exerciceId
     *
     * @param integer $exerciceId
     * @return CQuizRelQuestion
     */
    public function setExerciceId($exerciceId)
    {
        $this->exerciceId = $exerciceId;

        return $this;
    }

    /**
     * Get exerciceId
     *
     * @return integer
     */
    public function getExerciceId()
    {
        return $this->exerciceId;
    }

    /**
     * Set questionOrder
     *
     * @param integer $questionOrder
     * @return CQuizRelQuestion
     */
    public function setQuestionOrder($questionOrder)
    {
        $this->questionOrder = $questionOrder;

        return $this;
    }

    /**
     * Get questionOrder
     *
     * @return integer
     */
    public function getQuestionOrder()
    {
        return $this->questionOrder;
    }
}
