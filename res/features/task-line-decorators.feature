Feature: Using decorators
  In order to be able to wrap task lines
  As a user
  I need to be able to use decorators to wrap the task lines in whatever I want

Background:
  Given I am in a test directory
  Given there is a file "z.yml"
  """
  # @version ">=2.0"

  tasks:
    t:
      args:
          var: ? 4
      do:
        - @(sh "perl") print "." x 5 if $(var) % 2 == 0;
  """

  Scenario:
    When I run "z t"
    And I should see text matching "/\.{5}/"
    And I should not see text matching "/print/"

  Scenario:
    When I run "z t 3"
    And I should not see text matching "/\.{5}/"
