# What's an MVC Framework anyway?

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Model-View-Controller (MVC) is probably one of the most quoted patterns in the web programming world in recent years. Anyone currently working in anything related to web application development will have heard or read the acronym hundreds of times. Today, we'll clarify what MVC means, and why it has become so popular. 

Hazaar implements the MVC pattern in PHP, making it easy to build large scale web applications.

## History

> MVC is not a design pattern, it is an Architectural pattern that describes a way to structure our application and the responsibilities and interactions  for each part in that structure.
>
> --source: unknown

It was first described in 1979 and, obviously, the context was a little bit different. The concept of web application did not exist. Tim Berners Lee sowed the seeds of World Wide Web in the early nineties and changed the world forever. The pattern we use today for web development is an adaptation of the original pattern.

The wild popularization of this structure for web applications is due to its inclusion in two development frameworks that have become immensely popular: Struts and Ruby on Rails. These two environments marked the way for the hundreds of frameworks created later.

## MVC for Web Applications

The idea behind the Model-View-Controller architectural pattern is simple: we must have the following responsibilities clearly separated in our application:

The application is divided into these three main components, each one in charge of different tasks. Let's see a detailed explanation and an example.

### Controller

The Controller manages the user requests (received as HTTP GET or POST requests when the user clicks on GUI elements to perform actions). Its main function is to call and coordinate the necessary resources/objects needed to perform the user action. Usually the controller will call the appropriate model for the task and then selects the proper view.

### Model

The Model is the data and the rules applying to that data, which represent concepts that the application manages. In any software system, everything is modeled as data that we handle in a certain way. What is a user, a message or a book for an application? Only data that must be handled according to specific rules (date can not be in the future, e-mail must have a specific format, name cannot be more than x characters long, etc).

The model gives the controller a data representation of whatever the user requested (a message, a list of books, a photo album, etc). This data model will be the same no matter how we may want to present it to the user, that's why we can choose any available view to render it.

The model contains the most important part of our application logic, the logic that applies to the problem we are dealing with (a forum, a shop, a bank, etc). The controller contains a more internal-organizational logic for the application itself (more like housekeeping).

### View

The View provides different ways to present the data received from the model. They may be templates where that data is filled. There may be several different views and the controller has to decide which one to use.

A web application is usually composed of a set of controllers, models and views. The controller may be structured as a main controller that receives all requests and calls specific controllers that handles actions for each case.

# What is Hazaar?

Hazaar is a lightweight Model-View-Controller (MVC) framework written in PHP.  It was started in 2012 by Jamie Carl. It aims to provide a simple and efficient way to develop web applications using the [MVC architectural pattern](/guide/what-is-mvc). The framework focuses on simplicity, performance, and ease of use.

Hazaar follows the traditional MVC principles, where the Model represents the data and business logic, the View handles the presentation and user interface, and the Controller manages the communication between the Model and the View.  To get a better understanding of MVC, see: [What is MVC?](/guide/what-is-mvc).

```mermaid
---
title: Hazaar Architecture
---
flowchart TB
    User-->Routing
    User-->View
    subgraph Application
    Routing-->Controller
    Controller-->View
    Controller<-->Model
    end
    Model<-->Database
```

Hazaar also includes a number of other features that make it easy to build web applications.  These include:

* Support for RESTful APIs
* Built-in WebSockets server
* Support for Authentication and Authorisation

## Philisosphy

Hazaar is designed to be simple and easy to use.  It is not designed to be a full featured framework like Laravel or Symfony.  It is designed to be a simple and lightweight framework that can be used to build simple web applications quickly and easily.
