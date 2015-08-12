<?php

/**
 *  ASSESSMODELTEST.PHP
 *  @author Jonathan Lamb
 */
class AssessModelTest extends PHPUnit_Framework_TestCase {

  // store instantiated class and DB connection as instance variables
  private $_DB,
    $_AssessModel,
    $_UserModel;

  /**
   *  Constructor
   *  Initialise instance variables and create sample test, keeping references to common variables
   */
  public function __construct() {

    $this->_DB = DB::getInstance();
    $this->_AssessModel = new AssessModel();
    $this->_UserModel = new UserModel();
  }

  /**
   *  @test
   */
  public function _confirmStart() {
    print_r(" - start of AssessModel Test -  \n");
  }

  /**
   *  @test
   *  Create required MongoDB entries once only
   */
  public function _createMongoDBentries_methodsReturnTrue() {

    // create users
    $this->_UserModel->createUser("testAuthor", "password", "Test Author");
    $this->_UserModel->createUser("testStudent", "password", "Test Student One");
    $this->_UserModel->createUser("testStudent2", "password", "Test Student Two");
    $this->_UserModel->createUser("testStudent3", "password", "Test Student Three");
    $this->_UserModel->createUser("testStudent4", "password", "Test Student Four");

    // get user id's
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testStudent");
    $studentIdAvailable = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testStudent2");
    $studentIdTaken = $this->_UserModel->getUserData()->userId;

    // use 4th student to register as available but not to take test
    $this->_UserModel->findUser("testStudent4");
    $studentIdAvailableTwo = $this->_UserModel->getUserData()->userId;

    // create new questions
    $this->_DB->create("questions", array(
      "schema" => "boolean",
      "author" => $authorId,
      "statement" => "This sentence contains no vowels",
      "singleAnswer" => "FALSE",
      "feedback" => "The sentence contains 2x 'i', 4x 'e', 3x 'o' and 1x 'a'"
    ));
    $this->_DB->create("questions", array(
      "schema" => "boolean",
      "author" => $authorId,
      "statement" => "This sentence contains 10 vowels",
      "singleAnswer" => "TRUE",
      "feedback" => "Count the instances of 'a', 'e', 'i', 'o' and 'u'"
    ));
    $this->_DB->create("questions", array(
      "schema" => "boolean",
      "author" => $authorId,
      "statement" => "This sentence contains a jam sandwich",
      "singleAnswer" => "FALSE",
      "feedback" => "Clue: you cannot eat the question"
    ));

    // get the question id's
    $documents = $this->_DB->read("questions", array("author" => $authorId));

    // create a test
    $this->assertTrue($this->_DB->create("tests", array(
      "schema" => "standard",
      "author" => $authorId,
      "questions" => array_keys($documents)
    )));

    // get the test id
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // register the student id with the test
    $this->assertTrue($this->_DB->update(
      "tests",
      array("_id" => new MongoId($testId)),
      array("available" => array($studentIdAvailable, $studentIdAvailableTwo)
    )));

    // update test with example user that would have taken the test
    $this->assertTrue($this->_DB->update(
      "tests",
      array("_id" => new MongoId($testId)),
      array("taken" => array($studentIdTaken => "3")
    )));
  }

  /**
   *  @test
   *  Check that the list of available tests returns user
   */
  public function getListOfAvailableTests_checkWithValidStudent_methodReturnsMatchingValue() {

    // get test and student id
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));
    $this->_UserModel->findUser("testStudent");
    $studentId = $this->_UserModel->getUserData()->userId;

    // "{\"{$testId}\":\"available\"}",

    $this->assertSame(
      array(
        "{$testId}"
      ),
      $this->_AssessModel->getListOfAvailableTests($studentId)
    );
  }

  /**
   *  @test
   *  Check that a user not enrolled on any tests
   */
  public function getListOfAvailableTests_checkWithStudentNoTests_methodReturnsSpecificString() {

    $this->_UserModel->findUser("testStudent2");
    $studentId = $this->_UserModel->getUserData()->userId;

    $this->assertSame(
      "There are no tests available for you to take right now. Please try again later.",
      $this->_AssessModel->getListOfAvailableTests($studentId)
    );
  }

  /**
   *  @test
   *  Check that a user string that doesn't match hexadecimal returns false
   */
  public function getListOfAvailableTests_checkWithInvalidUserId_methodReturnsSpecificString() {

    $this->assertSame(
      "There are no tests available for you to take right now. Please try again later.",
      $this->_AssessModel->getListOfAvailableTests("<script>alert('hi');</script>")
    );
  }

  /**
   *  @test
   *  Check if a user is eligible to take a test
   */
  public function checkTestAvailable_checkWithValidStudent_methodReturnsTrue() {

    // get author, test and student id
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));
    $this->_UserModel->findUser("testStudent");
    $studentIdReady = $this->_UserModel->getUserData()->userId;

    $result = $this->_AssessModel->checkTestAvailable(
      new MongoId($testId),
      $studentIdReady
    );
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Check if a user that is not registered with a test is eligible
   */
  public function checkTestAvailable_checkWithIneligibleStudent_methodReturnsFalse() {

    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));
    $this->_UserModel->findUser("testStudent3");
    $studentIdNotRegistered = $this->_UserModel->getUserData()->userId;

    $result = $this->_AssessModel->checkTestAvailable(
      new MongoId($testId),
      $studentIdNotRegistered
    );
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Check if a user that has already taken the test is not eligible
   */
  public function checkTestAvailable_checkWithStudentTakenTest_methodReturnsFalse() {

    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));
    $this->_UserModel->findUser("testStudent2");
    $studentIdTaken = $this->_UserModel->getUserData()->userId;

    $result = $this->_AssessModel->checkTestAvailable(
      new MongoId($testId),
      $studentIdTaken
    );
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Check if a user has taken a test
   */
  public function checkTestTaken_checkWithStudentTakenTest_methodReturnsTrue() {

    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));
    $this->_UserModel->findUser("testStudent2");
    $studentIdTaken = $this->_UserModel->getUserData()->userId;

    $result = $this->_AssessModel->checkTestTaken(
      new MongoId($testId),
      $studentIdTaken
    );
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Check if a user has not taken a test
   */
  public function checkTestTaken_checkWithStudentTestAvailable_methodReturnsFalse() {

    // get author, test and student id
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));
    $this->_UserModel->findUser("testStudent");
    $studentIdReady = $this->_UserModel->getUserData()->userId;

    $result = $this->_AssessModel->checkTestTaken(
      new MongoId($testId),
      $studentIdReady
    );
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Check JSON data matches for a valid request for test data
   */
  public function getQuestionsJSON_validRequest_methodReturnsMatchingValues() {

    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    $this->assertSame(
      "{\"0\":{\"schema\":\"boolean\",\"statement\":\"This sentence contains no vowels\"}," .
      "\"1\":{\"schema\":\"boolean\",\"statement\":\"This sentence contains 10 vowels\"}," .
      "\"2\":{\"schema\":\"boolean\",\"statement\":\"This sentence contains a jam sandwich\"}}",
      $this->_AssessModel->getQuestionsJSON(new MongoId($testId))
    );
  }

  /**
   *  @test
   *  Attempt to get JSON of data for invalid test
   */
  public function getQuestionsJSON_invalidTestId_methodReturnsFalse() {

    $this->assertFalse($this->_AssessModel->getQuestionsJSON("903hngo4w3ngol3n0392"));
  }

  /**
   *  @test
   *  Submit answers to a test (valid)
   */
  public function updateAnswers_submitValidAnswers_methodReturnsTrueDocumentsUpdated() {

    // get Ids
    $this->_UserModel->findUser("testStudent");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // prepare answers (simulate PHP representation of JSON data)
    $input = new stdClass();
    $input->{0} = new stdClass();
    $input->{0}->{'uq'} = 1;    // this is an incorrect answer to a question...
    $input->{0}->{'ans'} = 'TRUE';
    $input->{1} = new stdClass();
    $input->{1}->{'uq'} = 1;
    $input->{1}->{'ans'} = 'FALSE';
    $input->{2} = new stdClass();
    $input->{2}->{'uq'} = 0;
    $input->{2}->{'ans'} = 'FALSE';

    $this->assertSame(
      "{\"score\":1,\"feedback\":" .
      "{\"0\":\"The sentence contains 2x 'i', 4x 'e', 3x 'o' and 1x 'a'\"," .
      "\"1\":\"Count the instances of 'a', 'e', 'i', 'o' and 'u'\"}}",
      $this->_AssessModel->updateAnswers(new MongoId($testId), $studentId, $input)
    );

    // check that the user's answer was marked and the question document was updated
    $questionToCheck = array_pop($this->_DB->read("questions", array("statement" => "This sentence contains no vowels")));
    $this->assertSame(
      0,
      $questionToCheck["taken"][$studentId]["ca"]
    );

    // check that the test document has been updated as well containing the total number of correct answers
    $testToCheck = array_pop($this->_DB->read("tests", array("author" => $authorId)));
    $this->assertSame(
      1,
      $testToCheck["taken"][$studentId]
    );
  }

  /**
   *  @test
   *  Attempt to submit answers to a test where students are not eligible to take it
   */
  public function updateAnswers_studentsNotEligible_methodReturnsFalse() {

    // get test id
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // prepare answers (simulate PHP representation of JSON data)
    $input = new stdClass();
    $input->{0} = new stdClass();
    $input->{0}->{'uq'} = 1;    // this is an incorrect answer to a question...
    $input->{0}->{'ans'} = 'TRUE';
    $input->{1} = new stdClass();
    $input->{1}->{'uq'} = 1;
    $input->{1}->{'ans'} = 'FALSE';
    $input->{2} = new stdClass();
    $input->{2}->{'uq'} = 0;
    $input->{2}->{'ans'} = 'FALSE';

    // get ineligible student ids
    $this->_UserModel->findUser("testStudent2");
    $studentIdTaken = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testStudent3");
    $studentIdNotInvolved = $this->_UserModel->getUserData()->userId;

    $this->assertFalse($this->_AssessModel->updateAnswers(new MongoId($testId), $studentIdTaken, $input));
    $this->assertFalse($this->_AssessModel->updateAnswers(new MongoId($testId), $studentIdNotInvolved, $input));
  }

  /**
   *  @test
   *  Attempt to submit invalid JSON as answers to a test
   */
  public function updateAnswers_submitInvalidInput_methodReturnsFalse() {

    $this->_UserModel->findUser("testStudent4");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    $this->assertFalse($this->_AssessModel->updateAnswers(new MongoId($testId), $studentId, $input = "Invalid JSON: Syntax error"));
  }

  /**
   *  @test
   *  Attempt to submit missing questions (root + answer and 'understanding of question')
   */
  public function updateAnswers_missingQuestion_methodReturnsFalse() {

    $this->_UserModel->findUser("testStudent4");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // prepare answers (simulate PHP representation of JSON data)
    $input = new stdClass();
    $input->{0} = new stdClass();
    $input->{0}->{'uq'} = 1;
    $input->{0}->{'ans'} = 'TRUE';
    $input->{2} = new stdClass();
    $input->{2}->{'uq'} = 0;
    $input->{2}->{'ans'} = 'FALSE';

    $this->assertFalse($this->_AssessModel->updateAnswers(new MongoId($testId), $studentId, $input));
  }

  /**
   *  @test
   *  Attempt to submit a single missing answer or 'understanding of question'
   */
  public function updateAnswers_missingAnswerOrUQ_methodReturnsFalse() {

    $this->_UserModel->findUser("testStudent4");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // prepare answers (simulate PHP representation of JSON data)
    $inputOne = new stdClass();
    $inputOne->{0} = new stdClass();
    $inputOne->{0}->{'uq'} = 1;    // missing answer
    $inputOne->{1} = new stdClass();
    $inputOne->{1}->{'uq'} = 1;
    $inputOne->{1}->{'ans'} = 'FALSE';
    $inputOne->{2} = new stdClass();
    $inputOne->{2}->{'uq'} = 0;
    $inputOne->{2}->{'ans'} = 'FALSE';

    $this->assertFalse($this->_AssessModel->updateAnswers(new MongoId($testId), $studentId, $inputOne));

    $inputTwo = new stdClass();
    $inputTwo->{0} = new stdClass();
    $inputTwo->{0}->{'uq'} = 1;
    $inputTwo->{0}->{'ans'} = 'TRUE';
    $inputTwo->{1} = new stdClass();
    $inputTwo->{1}->{'uq'} = 1;
    $inputTwo->{1}->{'ans'} = 'FALSE';
    $inputTwo->{2} = new stdClass();
    $inputTwo->{2}->{'ans'} = 'FALSE';   // missing 'understanding of question'

    $this->assertFalse($this->_AssessModel->updateAnswers(new MongoId($testId), $studentId, $inputTwo));
  }

  /**
   *  @test
   *  Attempt to submit an invalid answer or 'understanding of question'
   */
  public function updateAnswers_invalidAnswerOrUQ_methodReturnsFalse() {

    $this->_UserModel->findUser("testStudent4");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // prepare answers (simulate PHP representation of JSON data)
    $inputOne = new stdClass();
    $inputOne->{0} = new stdClass();
    $inputOne->{0}->{'uq'} = 1;
    $inputOne->{0}->{'ans'} = 'goats';   // invalid answer
    $inputOne->{1} = new stdClass();
    $inputOne->{1}->{'uq'} = 1;
    $inputOne->{1}->{'ans'} = 'FALSE';
    $inputOne->{2} = new stdClass();
    $inputOne->{2}->{'uq'} = 0;
    $inputOne->{2}->{'ans'} = 'FALSE';

    $this->assertFalse($this->_AssessModel->updateAnswers(new MongoId($testId), $studentId, $inputOne));

    $inputTwo = new stdClass();
    $inputTwo->{0} = new stdClass();
    $inputTwo->{0}->{'uq'} = 1;
    $inputTwo->{0}->{'ans'} = 'TRUE';
    $inputTwo->{1} = new stdClass();
    $inputTwo->{1}->{'uq'} = 2;        // invalid 'understanding of question'
    $inputTwo->{1}->{'ans'} = 'FALSE';
    $inputTwo->{2} = new stdClass();
    $inputTwo->{2}->{'uq'} = 0;
    $inputTwo->{2}->{'ans'} = 'FALSE';

    $this->assertFalse($this->_AssessModel->updateAnswers(new MongoId($testId), $studentId, $inputTwo));
  }

  /**
   *  @test
   *  Submit student feedback for questions (valid)
   */
  public function updateFeedback_submitValidInput_methodReturnsTrueDocumentUpdated() {

    $this->_UserModel->findUser("testStudent");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // simulate feedback from student by creating PHP object, representative of valid, parsed JSON
    $studentFeedback = new stdClass();
    $studentFeedback->{0} = 1;
    $studentFeedback->{1} = 0;
    $studentFeedback->{2} = 1;

    // check method returns true, check question document retains student answer and new feedback data
    $this->assertTrue($this->_AssessModel->updateFeedback(new MongoId($testId), $studentId, $studentFeedback));
    $questionToCheck = array_pop($this->_DB->read("questions", array("statement" => "This sentence contains no vowels")));
    $this->assertSame(
      0,
      $questionToCheck["taken"][$studentId]["ca"]
    );
    $this->assertSame(
      1,
      $questionToCheck["taken"][$studentId]["uf"]
    );
  }

  /**
   *  @test
   *  Attempt to submit feedback for a student that has not taken a test
   */
  public function updateFeedback_studentHasntTakenTest_methodReturnsFalse() {

    $this->_UserModel->findUser("testStudent3");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // simulate feedback from student by creating PHP object, representative of valid, parsed JSON
    $studentFeedback = new stdClass();
    $studentFeedback->{0} = 1;
    $studentFeedback->{1} = 0;
    $studentFeedback->{2} = 1;

    // check method returns true, check question document retains student answer and new feedback data
    $this->assertFalse($this->_AssessModel->updateFeedback(new MongoId($testId), $studentId, $studentFeedback));
  }

  /**
   *  @test
   *  Attempt to submit invalid JSON as feedback to test
   */
  public function updateFeedback_submitInvalidInput_methodReturnsFalse() {

    $this->_UserModel->findUser("testStudent");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    $this->assertFalse($this->_AssessModel->updateFeedback(new MongoId($testId), $studentId, "Invalid JSON: Syntax error"));
  }

  /**
   *  @test
   *  Attempt to submit invalid feedback values to test
   */
  public function updateFeedback_invalidFeedbackValues_methodReturnsFalse() {

    $this->_UserModel->findUser("testStudent");
    $studentId = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("testAuthor");
    $authorId = $this->_UserModel->getUserData()->userId;
    $testId = key($this->_DB->read("tests", array("author" => $authorId)));

    // simulate feedback from student by creating PHP object, representative of valid, parsed JSON
    $studentFeedback = new stdClass();
    $studentFeedback->{0} = 1;
    $studentFeedback->{1} = -4;
    $studentFeedback->{2} = 1;

    $this->assertFalse($this->_AssessModel->updateFeedback(new MongoId($testId), $studentId, $studentFeedback));
  }

  /**
   *  @test
   *  Drop Questions, Tests and Users collections (reset for later testing)
   */
  public function _dropCollections_methodsReturnTrue() {

    $dropQuestionsResult = $this->_DB->delete("questions", "DROP COLLECTION");
    $dropTestsResult = $this->_DB->delete("tests", "DROP COLLECTION");
    $dropUsersResult = $this->_DB->delete("users", "DROP COLLECTION");
    $this->assertTrue($dropQuestionsResult && $dropTestsResult && $dropUsersResult);
  }

  /**
   *  @test
   */
  public function _confirmEnd() {
    print_r("\n  - end of AssessModel Test -  \n\n");
  }
}
