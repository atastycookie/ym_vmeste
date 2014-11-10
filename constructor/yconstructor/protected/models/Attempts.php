<?php

/**
 * This is the model class for table "attempts".
 *
 * The followings are the available columns in table 'attempts':
 * @property integer $id
 * @property string $ip
 * @property integer $time
 */
class Attempts extends CActiveRecord
{
    const TIME_LIMIT = 60;

    public static function check()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $row = self::model()->findBySql("SELECT time FROM `".self::model()->tableName()."`
                                        WHERE ip = '".$ip."' ORDER BY time DESC LIMIT 1;");
        if(($row && ($row['time'] + self::TIME_LIMIT) < time()) || !$row)
            return false;
        else
            return true;
    }

    public static function add()
    {
        $newrow = new Attempts();
        $newrow->ip = $_SERVER['REMOTE_ADDR'];
        $newrow->time = time();
        $newrow->save();
    }

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'attempts';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('ip, time', 'required'),
			array('ip', 'length', 'max'=>255),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id, ip, time', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'ip' => 'IP',
			'time' => 'Time',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * Typical usecase:
	 * - Initialize the model fields with values from filter form.
	 * - Execute this method to get CActiveDataProvider instance which will filter
	 * models according to data in model fields.
	 * - Pass data provider to CGridView, CListView or any similar widget.
	 *
	 * @return CActiveDataProvider the data provider that can return the models
	 * based on the search/filter conditions.
	 */
	public function search()
	{
		// @todo Please modify the following code to remove attributes that should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('id',$this->id);
		$criteria->compare('ip',$this->ip,true);
		$criteria->compare('time',$this->time,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Settings the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}