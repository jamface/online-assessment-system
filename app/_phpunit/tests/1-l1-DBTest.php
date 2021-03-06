<?php

/**
 *  DBTEST.PHP
 *  @author Jonathan Lamb
 */
class DBTest extends PHPUnit_Framework_TestCase {

  // store instantiated class and examples as instance variables
  private $_DB;
  private $_sampleDocumentOne;
  private $_sampleDocumentTwo;
  private $_sampleDocumentThree;

  /**
   *  Constructor
   *  Initialise instance variables
   */
  public function __construct() {

    $this->_DB = DB::getInstance();
    $this->_sampleDocumentOne = array(
      "name" => "sample one",
      "testArray" => array(
        "arrayValueOne" => "1234",
        "arrayValueTwo" => "a String"
      )
    );
    $this->_sampleDocumentTwo = array(
      "name" => "sample two",
      "extra" => "document extra property",
      "testArray" => array(
        "arrayValueOne" => "another String",
        "arrayValueTwo" => "5678",
        "arrayValueThree" => "abcdefg"
      )
    );
    $this->_sampleDocumentThree = array(
      "name" => "sample three",
      "extra" => "document extra property"
    );
  }

  /**
   *  @test
   */
  public function _confirmStart() {
    print_r(" - start of DB Test -  \n");
  }

  /**
   *  @test 1.1
   *  Check that the getInstance factory method returns the same object reference
   */
  public function getInstance_callMethodTwice_sameObjectReturned() {

    $dbRefTwo = DB::getInstance();
    $this->assertEquals($this->_DB, $dbRefTwo);
  }

  /**
   *  @test 1.2
   *  Insert a single document (valid collection)
   */
  public function create_insertSingleDocument_returnsTrue() {

    $result = $this->_DB->create("samples", $this->_sampleDocumentOne);
    $this->assertTrue($result);
  }

  /**
   *  @test 1.3
   *  Insert multiple documents (valid collection)
   */
  public function create_insertMultipleDocuments_returnsTrue() {

    $result = $this->_DB->create("samples", array(
      $this->_sampleDocumentTwo,
      $this->_sampleDocumentThree,
    ));
    $this->assertTrue($result);
  }

  /**
   *  @test 1.4
   *  Attempt to insert an invalid document (standard object)
   */
  public function create_insertStandardObjectAsDocument_returnsSpecificString() {

    $object = new stdClass();
    $result = $this->_DB->create("samples", $object);
    $this->assertSame(
      "Document variable invalid: supply an array of associate arrays, or a single assoc. array",
      $result
    );
  }

  /**
   *  @test 1.5
   *  Attempt single document insert to an invalid collection
   */
  public function create_attemptInsertWithInvalidCollection_returnsSpecificString() {

    $result = $this->_DB->create("bananas", $this->_sampleDocumentOne);
    $this->assertSame(
      "'bananas' is an invalid collection",
      $result
    );
  }

  /**
   *  @test 1.6
   *  Get all documents in a collection
   */
  public function read_getAllDocumentsInCollection_arraySizeThree() {

    $documents = $this->_DB->read("samples", "ALL DOCUMENTS");
    $this->assertEquals(3, count($documents));
  }

  /**
   *  @test 1.7
   *  Get a specific document in a collection
   */
  public function read_getSpecificDocument_arraySizeOne() {

    $documents = $this->_DB->read("samples", array("name" => "sample two"));
    $this->assertEquals(1, count($documents));
  }

  /**
   *  @test 1.8
   *  Attempt to get documents with invalid conditions (standard object)
   */
  public function read_getDocumentsInvalidConditions_returnsSpecificString() {

    $object = new stdClass();
    $result = $this->_DB->read("samples", $object);
    $this->assertSame(
      "Read conditions are invalid: supply a valid associative array or 'ALL DOCUMENTS'",
      $result
    );
  }

  /**
   *  @test 1.9
   *  Update documents using conditions
   */
  public function update_changeDocumentValues_returnsTrue() {

    $result = $this->_DB->update(
      "samples",
      array(
        "extra" => "document extra property"
      ),
      array(
        "extra" => "things have changed"
      )
    );
    $this->assertTrue($result);
  }

  /**
   *  @test 1.10
   *  Attempt an update with no conditions or updates
   */
  public function update_attemptUpdateNoConditionsOrUpdates_returnsSpecificString() {

    $result = $this->_DB->update("samples");
    $this->assertSame(
      "Updates and Conditions are invalid: supply two valid associative arrays",
      $result
    );
  }

  /**
   *  @test 1.11
   *  Update an array by copying contents and overwritting the existing array
   */
  public function update_overwriteExistingArray_arrayLengthEqualsFour() {

    // get the mongo id for the 2nd object
    $document = array_pop($this->_DB->read("samples", array("name" => "sample two")));
    $newArray = $document["testArray"];
    $newArray["arrayValueFour"] = "here's another array value, again!";

    $this->_DB->update(
      "samples",
      array(
        "name" => "sample two"
      ),
      array(
        "testArray" => $newArray
      )
    );

    $document = array_pop($this->_DB->read("samples", array("name" => "sample two")));
    $this->assertEquals(
      4,
      count($document["testArray"])
    );
  }

  /**
   *  @test 1.12
   *  Create additional field using update operation
   */
  public function update_createAdditionalField_arrayLengthEqualsTwo() {

    $this->_DB->update(
      "samples",
      array(
        "name" => "sample two"
      ),
      array(
        "brandNewArray" => array(
          "valueOne", "valueTwo"
        )
      )
    );

    $document = array_pop($this->_DB->read("samples", array("name" => "sample two")));
    $this->assertEquals(
      2,
      count($document["brandNewArray"])
    );
  }

  /**
   *  @test 1.13
   *  Delete single document from collection with condition
   */
  public function delete_deleteSingleRecord_returnsTrue() {

    $result = $this->_DB->delete("samples", array(
      "name" => "sample three"
    ));
    $this->assertTrue($result);
  }

  /**
   *  @test 1.14
   *  Drop collection; delete remaining documents inserted
   */
  public function delete_dropCollection_returnsTrue() {

    $result = $this->_DB->delete("samples", "DROP COLLECTION");
    $this->assertTrue($result);
  }

  /**
   *  @test 1.15
   *  Attempt drop of invalid collection
   */
  public function delete_dropInvalidCollection_returnsSpecificString() {

    $result = $this->_DB->delete("bananas", "DROP COLLECTION");
    $this->assertSame(
      "'bananas' is an invalid collection",
      $result
    );
  }

  /**
   *  @test 1.16
   *  Attempt to delete with invalid 2nd parameter (not an array)
   */
  public function delete_attemptWithInvalidSecondParam_returnsSpecificString() {

    $object = new stdClass();
    $result = $this->_DB->delete("samples", $object);
    $this->assertSame(
      "Delete conditions are invalid: supply a valid associative array or 'DROP COLLECTION'",
      $result
    );
  }

  /**
   *  @test
   */
  public function _confirmEnd() {
    print_r("\n  - end of DB Test -  \n");
  }
}
