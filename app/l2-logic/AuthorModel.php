<?php

/**
 *  AUTHORMODEL.PHP
 *  Create, get, update and delete questions and tests
 *  Register users to be eligible to take tests
 *  @author Jonathan Lamb
 */
class AuthorModel {

  // store DB utility, question and test schema as instance variables
  private $_DB,
    $_QuestionSchema,
    $_TestSchema;

  /**
   *  Constructor
   *  Initialise instance variables
   */
  public function __construct() {

    // store instance of DB class for CRUD operations
    $this->_DB = DB::getInstance();

    // import question and test schema: restrict document structure of questions
    $this->_QuestionSchema = new QuestionSchema();
    $this->_TestSchema = new TestSchema();
  }

  #############################################
  ################# QUESTIONS #################
  #############################################

  /**
   *  CREATE A QUESTION
   *  Create a question if the schema is valid and the question values follow schema requirements
   *  @return true (boolean) on success, else false
   */
  public function createQuestion($question = array()) {

    // fail the operation if the user did not provide a schema key, otherwise check if schema is recognised
    if (!isset($question["schema"])) return false;
    if (in_array($question["schema"], $this->_QuestionSchema->getSchemaList())) {

      // prepare document to insert (valid data will be transfered to this new variable)
      $document = array();

      // loop through each schema property
      foreach ($this->_QuestionSchema->getSchema($question["schema"]) as $sProperty => $sRequirement) {

        // if a required property was not provided, fail the operation
        if ($sRequirement === "required" && !isset($question[$sProperty])) return false;

        // if property is required or property is optional AND there is a value that can be used
        if ($sRequirement === "required" ||
          ($sRequirement === "optional" && isset($question[$sProperty]))) {

          // copy item from the question to the insertion document and remove it from question array
          $document[$sProperty] = $question[$sProperty];
          unset($question[$sProperty]);
        }
      }

      // any remaining data is not part of the schema, fail the operation
      if (!empty($question)) return false;

      // otherwise the question provided was valid, return the result of the DB operation
      return $this->_DB->create("questions", $document);
    }

    return false;
  }

  /**
   *  GET (READ) QUESTIONS
   *  Get all questions matching a user ID
   *  @return document(s) as PHP array (empty if no docs)
   */
  public function getQuestions($userIdStr) {

    // check userId contains hexadecimal characters only (fail if otherwise)
    if (preg_match('/^([a-z0-9])+$/', $userIdStr) === 0) return false;

    // return data
    return $this->_DB->read("questions", array("author" => $userIdStr));
  }

  /**
   *  UPDATE A QUESTION
   *  Update the value of a single question (expects MongoId object)
   *  If key exists in schema and operation is permitted
   *  @return true (boolean) on success, else false
   */
  public function updateQuestion($questionIdObj, $update = array()) {

    // check questionIdObj is MongoId object
    if (is_a($questionIdObj, 'MongoId')) {

      // identify the schema of the question
      $document = $this->_DB->read("questions", array("_id" => $questionIdObj));
      if (empty($document)) return false;
      $schema = $document[key($document)]["schema"];

      // only continue if the update complies with the schema AND it isn't an author or schema update
      if (array_key_exists(key($update), $this->_QuestionSchema->getSchema($schema))
        && key($update) !== "author" && key($update) !== "schema") {

        // return the result of the update operation
        return $this->_DB->update("questions", array("_id" => $questionIdObj), $update);
      }
    }

    return false;
  }

  /**
   *  DELETE A QUESTION
   *  Delete a single question (expects MongoId object) if it is the author's question
   *  @return true (boolean) on success, else false
   */
  public function deleteQuestion($questionIdObj, $authorIdStr) {

    // check questionIdObj is MongoId object
    if (is_a($questionIdObj, 'MongoId')) {

      // identify the author of the question
      $document = $this->_DB->read("questions", array("_id" => $questionIdObj));
      $author = $document[key($document)]["author"];

      // permit delete operation if the author ID matches
      if ($authorIdStr === $author) {

        // return the result of the delete operation
        return $this->_DB->delete("questions", array("_id" => $questionIdObj));
      }
    }

    return false;
  }

  #########################################
  ################# TESTS #################
  #########################################

  /**
   *  CREATE A TEST
   *  Create a test if the schema is valid and the test values follow schema requirements
   *  @return true (boolean) on success, else false
   */
  public function createTest($test = array()) {

    // fail the operation if the user did not provide a schema key, otherwise check if schema is recognised
    if (!isset($test["schema"])) return false;
    if (in_array($test["schema"], $this->_TestSchema->getSchemaList())) {

      // prepare document to insert (valid data will be transfered to this new variable)
      $document = array();

      // loop through each schema property
      foreach ($this->_TestSchema->getSchema($test["schema"]) as $tProperty => $tRequirement) {

        // if a required property was not provided, fail the operation
        if ($tRequirement === "required" && !isset($test[$tProperty])) return false;

        // if property is required or property is optional AND there is a value that can be used
        if ($tRequirement === "required" ||
         ($tRequirement === "optional" && isset($test[$tProperty]))) {

          // copy item from the question to the insertion document and remove it from question array
          $document[$tProperty] = $test[$tProperty];
          unset($test[$tProperty]);
        }
      }

      // any remaining data is not part of the schema, fail the operation
      if (!empty($test)) return false;

      // otherwise the test provided was valid, return the result of the DB operation
      return $this->_DB->create("tests", $document);
    }

    return false;
  }

  /**
   *  GET (READ) TESTS
   *  Get all tests matching a user ID
   *  @return document(s) as PHP array (empty if no docs)
   */
  public function getTests($userIdStr) {

    // check userId contains hexadecimal characters only (fail if otherwise)
    if (preg_match('/^([a-z0-9])+$/', $userIdStr) === 0) return false;

    // return data
    return $this->_DB->read("tests", array("author" => $userIdStr));
  }

  /**
   *  GET A SINGLE TEST
   *  Get a single test matching a test ID and user String
   *  @return document as PHP array, or empty if no match
   */
  public function getSingleTest($testIdObj, $userIdStr) {

    // if test ID object is MongoId and userId string is hexadecimal chars
    if (is_a($testIdObj, 'MongoId') &&
      preg_match('/^([a-z0-9])+$/', $userIdStr) === 1) {

      // return data
      return $this->_DB->read("tests", array(
        "_id" => $testIdObj,
        "author" => $userIdStr
      ));
    }

    return false;
  }

  /**
   *  GET FULL DETAILS FOR A SINGLE TEST
   *  Get full (summary) details, including question and user details through additional queries
   *  @return JSON on success, otherwise false (boolean)
   */
  public function getFullTestDetails($testIdObj, $userIdStr) {

    // if test ID object is MongoId and userId string is hexadecimal chars
    if (is_a($testIdObj, 'MongoId') && preg_match('/^([a-z0-9])+$/', $userIdStr) === 1) {

      // get tests from MongoDB
      $test = $this->_DB->read("tests", array(
        "_id" => $testIdObj
      ));
      if (empty($test)) return false;
      $test = array_pop($test);

      // check user and author match
      if ($test["author"] !== $userIdStr) return false;

      // create root response object
      $response = new stdClass();
      $response->{'questions'} = new stdClass();

      // loop through questions and append details to response object
      foreach ($test["questions"] as $questionId) {

        $document = $this->_DB->read("questions", array("_id" => new MongoId($questionId)));
        $document = array_pop($document);
        $response->{'questions'}->{$questionId} = new stdClass();
        $response->{'questions'}->{$questionId}->{'name'} = $document["name"];
        $response->{'questions'}->{$questionId}->{'type'} = ucfirst($document["schema"]);
        $response->{'questions'}->{$questionId}->{'question'} = $document["question"];
      }

      // add details about who the test has been issued to, if available
      if (isset($test["available"])) {

        // loop through users and add to response
        $issued = array();
        foreach ($test["available"] as $userId) {

          $document = $this->_DB->read("users", array("_id" => new MongoId($userId)));
          $document = array_pop($document);
          $issued[$userId] = $document["full_name"];
        }
        $response->{'issued'} = $issued;
      }

      // add details about who the test has been taken by, if available
      if (isset($test["taken"])) {

        // loop through users and add to response
        $taken = array();
        foreach ($test["taken"] as $userId => $details) {

          $document = $this->_DB->read("users", array("_id" => new MongoId($userId)));
          $document = array_pop($document);
          $taken[$userId] = $document["full_name"];
        }
        $response->{'taken'} = $taken;
      }

      return json_encode($response);
    }

    return false;
  }

  /**
   *  UPDATE A TEST
   *  Update the value of a test (expects MongoId object)
   *  If key exists in schema and operation is permitted
   *  @return true (boolean) on success, else false
   */
  public function updateTest($testIdObj, $update = array()) {

    // check testIdObj is MongoId object
    if (is_a($testIdObj, 'MongoId')) {

      // identify the schema of the test
      $document = $this->_DB->read("tests", array("_id" => $testIdObj));
      if (empty($document)) return false;
      $schema = $document[key($document)]["schema"];

      // if update contains questions and its value is not an array, fail the operation
      if (isset($update["questions"]))
        if (!is_array($update["questions"])) return false;

      // only continue if update complies with schema AND it isn't an author or schema update
      if (array_key_exists(key($update), $this->_TestSchema->getSchema($schema))
        && key($update) !== "author" && key($update) !== "schema") {

          // return the result of the update operation
          return $this->_DB->update("tests", array("_id" => $testIdObj), $update);
      }
    }

    return false;
  }

  /**
   *  GET STUDENTS THAT CAN OR HAVE TAKEN A TEST
   *  Get an array of student id's and names that are eligible to take a test
   *  @return JSON of values on success, otherwise returns false (boolean)
   */
  public function getStudentsForTest($testIdObj, $userIdStr) {

    // get test
    $test = $this->getSingleTest($testIdObj, $userIdStr);
    $test = array_pop($test);
    if ($test === false) return false;

    // get groups
    $groups = $this->_DB->read("groups", "ALL DOCUMENTS");

    // get list of students
    $users = $this->_DB->read("users", array("account_type" => "student"));
    $userRoot = new stdClass();
    $userRoot->{'groups'} = new stdClass();
    $userRoot->{'students'} = new stdClass();

    foreach ($users as $uId => $details) {

      $checkGroups = false;
      if (isset($test["taken"])) {
        if (array_key_exists($uId, $test["taken"])) {

          $checkGroups = true;
        }
      }

      if (isset($test["available"])) {
        if (in_array($uId, $test["available"])) {

          $checkGroups = true;
        }
      }

      // only add users that have not taken the test or have not been registered
      if ($checkGroups) {

        foreach ($groups as $gId => $details) {
          if (in_array($uId, $details["members"])) {

            unset($groups[$gId]);
          }
        }

      } else {

        $userRoot->{'students'}->{$uId} = $details["full_name"];
      }
    }

    // add remaining group id's and names to response Object
    foreach ($groups as $gId => $details) {

      $userRoot->{'groups'}->{$gId} = $details["name"];
    }

    return json_encode($userRoot);
  }

  /**
   *  MAKE A TEST AVAILABLE TO A USER
   *  Register a user id with a test so they may take it
   *  @return true (boolean) on success, else false
   */
  public function makeTestAvailableToUser($testIdObj, $studentIdObj) {

    // check testIdObj and studentIdObj are MongoIds
    if (is_a($testIdObj, 'MongoId') && is_a($studentIdObj, 'MongoId')) {

      // check that queries for the test and student account return one result only
      $test = $this->_DB->read("tests", array("_id" => $testIdObj));
      if (count($test) !== 1) return false;
      $test = array_pop($test);

      $student = $this->_DB->read("users", array("_id" => $studentIdObj));
      if (count($student) !== 1) return false;
      $student = array_pop($student);

      // check if the student has already taken the test
      if (isset($test["taken"]))
        if (in_array($student["_id"]->{'$id'}, $test["taken"])) return false;

      // copy the availability array and add new student if it exists
      if (isset($test["available"])) {

        $availableArray = $test["available"];
        $availableArray[] = $student["_id"]->{'$id'};

      } else {

        // otherwise create a new array
        $availableArray = array($student["_id"]->{'$id'});
      }

      // return the result of the database operation
      return $this->_DB->update("tests", array("_id" => $testIdObj), array("available" => $availableArray));
    }

    return false;
  }

  /**
   *  MAKE A TEST AVAILABLE TO A GROUP
   *  Register user ids with a test so they may take it
   *  @return true (boolean) on success, else false
   */
  public function makeTestAvailableToGroup($testIdObj, $groupIdObj) {

    // check testIdObj and $groupIdObj are MongoIds
    if (is_a($testIdObj, 'MongoId') && is_a($groupIdObj, 'MongoId')) {

      // check that queries for the test and group return one result only
      $test = $this->_DB->read("tests", array("_id" => $testIdObj));
      if (count($test) !== 1) return false;
      $test = array_pop($test);

      $group = $this->_DB->read("groups", array("_id" => $groupIdObj));
      if (count($group) !== 1) return count($group);
      $group = array_pop($group);

      // check if any group member has already taken the test
      $addStudentArray = array();
      foreach ($group["members"] as $sId) {

        $user = $this->_DB->read("users", array("_id" => new MongoId($sId)));
        $user = array_pop($user);

        if (isset($test["taken"]))
          if (in_array($user["_id"]->{'$id'}, $test["taken"])) return false;

        if (isset($test["available"]))
          if (in_array($user["_id"]->{'$id'}, $test["available"])) return false;

        $addStudentArray[] = $sId;
      }

      // copy the availability array and add new students if it exists
      if (isset($test["available"])) {

        $availableArray = $test["available"];
        foreach ($addStudentArray as $sId) {
          $availableArray[] = $sId;
        }

      } else {

        // otherwise create a new array
        $availableArray = $addStudentArray;
      }

      // return the result of the database operation
      return $this->_DB->update("tests", array("_id" => $testIdObj), array("available" => $availableArray));
    }

    return false;
  }

  /**
   *  DELETE A TEST
   *  Delete a single test (expects MongoId object) if it is the author's test
   *  @return true (boolean) on success, else false
   */
  public function deleteTest($testIdObj, $authorIdStr) {

    // check testIdObj is MongoId object
    if (is_a($testIdObj, 'MongoId')) {

      // identify the author of the question
      $document = $this->_DB->read("tests", array("_id" => $testIdObj));
      $author = $document[key($document)]["author"];

      // permit delete operation if the author ID matches
      if ($authorIdStr === $author) {

        // return the result of the delete operation
        return $this->_DB->delete("tests", array("_id" => $testIdObj));
      }
    }

    return false;
  }
}
