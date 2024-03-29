<?php
/**
 * Created by PhpStorm.
 * Authors: Eugene Avrukevich <eugene.avrukevich@gmail.com>
 * Date: 9/23/14
 * Time: 10:25 PM
 */

namespace Vmeste\SaasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Vmeste\SaasBundle\Entity\SettingsRepository")
 * @ORM\Table(name="settings")
 */
class Settings
{

    /**
     * @ORM\Column(type="integer", name="id", options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", nullable=true , name="company_name")
     */
    protected $companyName;

    /**
     * @ORM\Column(type="text", nullable=true , name="director_name")
     */
    protected $directorName;

    /**
     * @ORM\Column(type="text", nullable=true , name="position")
     */
    protected $position;

    /**
     * @ORM\Column(type="text", nullable=true , name="authority")
     */
    protected $authority;

    /**
     * @ORM\Column(type="text", nullable=true , name="details", length=1024)
     */
    protected $details;

    /**
     * @ORM\Column(type="string", nullable=true, name="notification_email")
     */
    protected $notificationEmail = NULL;

    /**
     * @ORM\Column(type="string", nullable=true, name="sender_name")
     */
    protected $senderName = NULL;

    /**
     * @ORM\Column(type="string", nullable=true, name="sender_email")
     */
    protected $senderEmail = NULL;

    /**
     * @ORM\Column(type="string", length=8, name="csv_column_separator")
     */
    protected $csvColumnSeparator = ";"; //options={"default": ';' } unused

    /**
     * @ORM\OneToOne(targetEntity="YandexKassa")
     * @ORM\JoinColumn(name="yk_id", referencedColumnName="id")
     **/
    protected $yandexKassa;

    /**
     * @ORM\ManyToMany(targetEntity="User", mappedBy="settings")
     */
    protected $user;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set notificationEmail
     *
     * @param string $notificationEmail
     * @return Settings
     */
    public function setNotificationEmail($notificationEmail)
    {
        $this->notificationEmail = $notificationEmail;

        return $this;
    }

    /**
     * Get notificationEmail
     *
     * @return string
     */
    public function getNotificationEmail()
    {
        return $this->notificationEmail;
    }

    /**
     * Set senderName
     *
     * @param string $senderName
     * @return Settings
     */
    public function setSenderName($senderName)
    {
        $this->senderName = $senderName;

        return $this;
    }

    /**
     * Get senderName
     *
     * @return string
     */
    public function getSenderName()
    {
        return $this->senderName;
    }

    /**
     * Set senderEmail
     *
     * @param string $senderEmail
     * @return Settings
     */
    public function setSenderEmail($senderEmail)
    {
        $this->senderEmail = $senderEmail;

        return $this;
    }

    /**
     * Get senderEmail
     *
     * @return string
     */
    public function getSenderEmail()
    {
        return $this->senderEmail;
    }

    /**
     * Set csvColumnSeparator
     *
     * @param string $csvColumnSeparator
     * @return Settings
     */
    public function setCsvColumnSeparator($csvColumnSeparator)
    {
        $this->csvColumnSeparator = $csvColumnSeparator;

        return $this;
    }

    /**
     * Get csvColumnSeparator
     *
     * @return string
     */
    public function getCsvColumnSeparator()
    {
        return $this->csvColumnSeparator;
    }

    /**
     * Set yandexKassa
     *
     * @param \Vmeste\SaasBundle\Entity\YandexKassa $yandexKassa
     * @return Settings
     */
    public function setYandexKassa(\Vmeste\SaasBundle\Entity\YandexKassa $yandexKassa = null)
    {
        $this->yandexKassa = $yandexKassa;

        return $this;
    }

    /**
     * Get yandexKassa
     *
     * @return \Vmeste\SaasBundle\Entity\YandexKassa
     */
    public function getYandexKassa()
    {
        return $this->yandexKassa;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->user = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add user
     *
     * @param \Vmeste\SaasBundle\Entity\User $user
     * @return Settings
     */
    public function addUser(\Vmeste\SaasBundle\Entity\User $user)
    {
        $this->user[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \Vmeste\SaasBundle\Entity\User $user
     */
    public function removeUser(\Vmeste\SaasBundle\Entity\User $user)
    {
        $this->user->removeElement($user);
    }

    /**
     * Get user
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set companyName
     *
     * @param string $companyName
     * @return Settings
     */
    public function setCompanyName($companyName)
    {
        $this->companyName = $companyName;

        return $this;
    }

    /**
     * Get companyName
     *
     * @return string
     */
    public function getCompanyName()
    {
        return $this->companyName;
    }

    /**
     * Set details
     *
     * @param string $details
     * @return Settings
     */
    public function setDetails($details)
    {
        $this->details = $details;

        return $this;
    }

    /**
     * Get details
     *
     * @return string
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Set directorName
     *
     * @param string $directorName
     * @return Settings
     */
    public function setDirectorName($directorName)
    {
        $this->directorName = $directorName;

        return $this;
    }

    /**
     * Get directorName
     *
     * @return string
     */
    public function getDirectorName()
    {
        return $this->directorName;
    }

    /**
     * Set position
     *
     * @param string $position
     * @return Settings
     */
    public function setPosition($position)
    {
        $this->position = $position;
    
        return $this;
    }

    /**
     * Get position
     *
     * @return string 
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set authority
     *
     * @param string $authority
     * @return Settings
     */
    public function setAuthority($authority)
    {
        $this->authority = $authority;
    
        return $this;
    }

    /**
     * Get authority
     *
     * @return string 
     */
    public function getAuthority()
    {
        return $this->authority;
    }
}
