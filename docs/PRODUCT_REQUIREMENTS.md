Tamam — Product Requirements Document

Document: PRODUCT_REQUIREMENTS.mdProduct: Tamam (تمام)Version: 1.0Status: PlanningInitial Market: QatarFuture Expansion: GCCDocument Language: English

Table of Contents

Introduction

Product Vision

Product Goals

Target Audience

User Roles

MVP Scope

Functional Requirements

Non-Functional Requirements

Authentication Module

User Profile Module

Categories Module

Listings Module

Search and Filters Module

Favorites Module

Messaging Module

Notifications Module

Reviews and Ratings Module

Reports and Moderation Module

Business Accounts Module

Premium Features Module

Payments Module

Admin Dashboard Module

Analytics and Reporting Module

Future Features

Global Acceptance Criteria

1. Introduction

Tamam is a modern classified marketplace platform designed to connect buyers, sellers, service providers, businesses, employers, and job seekers.

The platform will launch in Qatar and will be designed from the beginning to support future expansion across GCC countries.

Tamam will prioritise:

Simplicity

Trust

Speed

Mobile-first design

High-quality listings

Arabic and English support

Secure communication

Scalable architecture

The first release will include a responsive web platform. Native mobile applications may be developed after the web MVP has been validated.

2. Product Vision

To become the most trusted, simple, and modern marketplace for buying, selling, discovering services, and finding opportunities in Qatar and the GCC.

Tamam should feel easier, cleaner, and safer than traditional classified platforms.

The product should combine:

Fast listing creation

Powerful search

Strong trust features

Clear marketplace rules

Professional business tools

Modern user experience

3. Product Goals

3.1 Primary Goals

Allow users to publish listings quickly.

Help buyers find relevant listings easily.

Build trust between marketplace participants.

Support individual sellers and professional businesses.

Generate revenue through promotions and subscriptions.

Provide administrators with effective moderation tools.

Support Arabic and English from launch.

3.2 Business Goals

Build a strong user base in Qatar.

Increase the number of active quality listings.

Encourage repeat visits and repeat sellers.

Convert active sellers into paying customers.

Attract verified business accounts.

Establish Tamam as a recognisable marketplace brand.

3.3 Product Success Indicators

Active users

Published listings

Search-to-listing click rate

Buyer-to-seller conversation rate

Listing completion rate

Successful moderation rate

User retention

Paid promotion conversion

Business subscription conversion

User satisfaction and trust reports

4. Target Audience

4.1 Individual Buyers

People searching for:

Vehicles

Properties

Electronics

Furniture

Services

Jobs

General products

4.2 Individual Sellers

People who want to:

Sell personal items

Advertise services

Rent or sell property

Advertise vehicles

Publish job opportunities

Find buyers quickly

4.3 Businesses

Examples:

Car dealerships

Real-estate agencies

Shops

Service providers

Recruitment companies

Restaurants

Hotels

Professional sellers

4.4 Job Seekers and Employers

Job seekers can browse opportunities.

Employers and authorised businesses can publish job listings according to applicable rules.

4.5 Administrators and Moderators

Internal teams responsible for:

User management

Listing review

Fraud prevention

Customer support

Payments

Platform configuration

Reports and analytics

5. User Roles

5.1 Guest

A guest can:

Browse categories

Search listings

View public profiles

View listing details

Share listings

Register or log in

A guest cannot:

Publish listings

Save favourites

Send messages

Submit reviews

Report users through authenticated workflows

5.2 Registered User

A registered user can:

Manage a profile

Save favourites

Send messages

Create drafts

Manage account preferences

Phone verification is required before publishing listings.

5.3 Verified User

A verified user can:

Publish listings

Contact sellers

Receive trust badges

Use eligible marketplace features

5.4 Business Account

A business account can:

Create a business profile

Publish higher listing volumes

Access business analytics

Purchase subscription packages

Add authorised team members in future releases

Receive a verified business badge after approval

5.5 Moderator

A moderator can:

Review listings

Approve or reject content

Review reports

Suspend listings

Apply moderation actions

Record internal notes

5.6 Administrator

An administrator can:

Manage users

Manage categories

Manage listings

Manage packages

Manage payments

Configure platform rules

View analytics

Manage moderators

5.7 Super Administrator

A super administrator has complete platform access, including:

Roles and permissions

System configuration

Sensitive audit records

Administrator management

Critical account actions

6. MVP Scope

The MVP should include:

Arabic and English interfaces

Registration and login

Phone and email verification

User profiles

Category hierarchy

Dynamic category attributes

Listing creation and management

Listing images

Search and filters

Favourites

In-app messaging

Notifications

Reviews and ratings

Reporting and moderation

Business profiles

Basic paid promotions

Payment records

Administration dashboard

Basic analytics

The MVP will not require:

Native mobile applications

AI recommendations

Integrated delivery

Escrow payments

Marketplace wallet

Auctions

Loyalty programme

Advanced team accounts

Cross-border marketplace transactions

7. Functional Requirements

7.1 General Platform Requirements

The platform must:

Support responsive desktop, tablet, and mobile layouts.

Support Arabic RTL and English LTR.

Provide consistent navigation.

Use role-based permissions.

Protect private user information.

Record important actions in audit logs.

Support configurable marketplace rules.

Display clear validation and error messages.

Provide accessible loading, empty, success, and error states.

7.2 Localisation

The system must support:

Arabic

English

RTL layout for Arabic

LTR layout for English

Translated categories

Translated interface labels

Localised dates and numbers

QAR as the initial default currency

7.3 Public Pages

Public pages include:

Homepage

Category pages

Search results

Listing details

Public user profile

Public business profile

Terms and conditions

Privacy policy

Marketplace policies

Contact and support pages

8. Non-Functional Requirements

8.1 Performance

Targets:

Fast first-page load

Optimised images

Pagination or infinite loading for large lists

Cached categories and frequently requested data

Efficient database indexes

Background processing for media and notifications

8.2 Scalability

The architecture must support:

Increased user traffic

Increased listing volume

Multiple GCC countries

Multiple currencies

Additional languages

Search engine migration

Separate mobile applications

Additional payment providers

8.3 Security

The platform must:

Use HTTPS

Hash passwords securely

Validate all input

Apply authentication rate limits

Protect against common web vulnerabilities

Use permission-based administration access

Avoid exposing private data

Record security-sensitive actions

Revoke sessions after critical account changes

8.4 Reliability

The platform should:

Use database backups

Protect payment records

Provide reliable error handling

Retry eligible background jobs

Monitor failures

Preserve audit records

Support recovery procedures

8.5 Accessibility

The interface should:

Support keyboard navigation

Use readable contrast

Provide labels for form controls

Provide image alternative text where applicable

Avoid communicating status by colour alone

Use clear focus indicators

8.6 Browser Support

The web platform should support current stable versions of:

Chrome

Safari

Edge

Firefox

Mobile Safari

Chrome for Android

9. Authentication Module

9.1 Overview

The Authentication module manages identity, account access, verification, sessions, and password security.

9.2 Registration

Required fields:

Full name

Email

Mobile number

Password

Acceptance of terms

Validation:

Full name: 3–100 characters

Valid unique email

Valid unique phone number with country code

Password: minimum 8 characters

Password should include uppercase, lowercase, and number

Registration flow:

User enters information.

System validates the data.

Account is created as pending verification.

Phone OTP is sent.

User verifies the phone.

Account becomes active.

User is redirected to onboarding or dashboard.

9.3 Login

Users can log in with:

Email and password

Phone number and password

The system must:

Validate credentials

Apply rate limits

Reject blocked or deleted accounts

Create an authenticated session

Record login activity

9.4 Logout

Logout must:

Revoke the current session or token

Clear authentication data

Record the event where required

9.5 Phone Verification

Phone verification is mandatory before publishing.

OTP requirements:

Expires after 5 minutes

Maximum 5 failed attempts

Resend rate limit

OTP is never stored in plain text

9.6 Email Verification

Users receive a verification link or code.

Email verification is mandatory for business accounts.

9.7 Password Reset

Flow:

User requests a reset.

System sends a secure reset link or code.

User creates a new password.

Existing sessions are revoked.

User logs in again.

9.8 Account Statuses

Pending

Active

Suspended

Blocked

Deleted

9.9 Business Rules

A phone number cannot belong to multiple active accounts.

An email cannot belong to multiple active accounts.

Blocked users cannot log in.

Deleted users cannot log in.

Suspended users receive an appropriate message.

Phone verification is required before publishing.

Business accounts require phone and email verification.

9.10 API Endpoints

POST /auth/register

POST /auth/login

POST /auth/logout

POST /auth/refresh

POST /auth/forgot-password

POST /auth/reset-password

POST /auth/verify-phone

POST /auth/resend-phone-code

POST /auth/verify-email

GET /auth/me

9.11 Acceptance Criteria

Registration works.

Duplicate accounts are prevented.

Login and logout work.

Password reset works.

Phone and email verification work.

Rate limiting is enabled.

Blocked users cannot access authenticated features.

Authentication workflows are tested.

10. User Profile Module

10.1 Overview

Every registered user has one profile containing personal information, public marketplace details, settings, verification status, and seller activity.

10.2 Profile Information

Profile photo

Full name

Username

Biography

Phone

Email

Country

City

Preferred language

Member since

Verification badges

Seller statistics

10.3 Public Profile

Visible:

Profile photo

Display name

Member since

Rating

Verification badges

Active listings

Public seller statistics

Hidden:

Private email

Hidden phone number

Security settings

Login history

Internal moderation records

10.4 Edit Profile

Users can update:

Profile photo

Full name

Username

Biography

Country

City

Preferred language

Phone changes require re-verification.

Email changes require re-verification.

10.5 Profile Photo

Supported formats:

JPG

PNG

WEBP

Maximum size: 5 MB.

The system should compress the image, generate thumbnails, and optimise delivery.

10.6 Username Rules

Unique

3–30 characters

Letters, numbers, and underscore

No spaces

Prohibited words are rejected

10.7 Seller Statistics

Total listings

Active listings

Sold listings

Average rating

Review count

Response rate

Member since

Verification status

10.8 Privacy Settings

Users may control:

Phone visibility

Email visibility

Message permissions

Promotional email preferences

Notification preferences

Profile visibility where permitted

10.9 Account Security

Users can:

Change password

Change email

Change phone

Log out

Request account deletion

Future:

Device management

Login history

Logout all devices

10.10 Account Deletion

After confirmation:

Listings are deactivated.

Sessions are revoked.

Active subscriptions are handled according to policy.

Personal data is anonymised or retained according to legal obligations.

Financial and audit records are preserved when required.

10.11 API Endpoints

GET /users/me

PUT /users/me

PUT /users/me/avatar

PUT /users/me/password

DELETE /users/me

GET /users/me/listings

GET /users/me/favorites

GET /users/me/reviews

GET /users/{id}

10.12 Acceptance Criteria

Users can manage profiles.

Public and private data are separated.

Profile image processing works.

Verification badges are accurate.

Seller statistics are accurate.

Privacy preferences are respected.

Account deletion follows defined rules.

11. Categories Module

11.1 Overview

Categories organise all listings into a structured hierarchy.

Every listing belongs to exactly one final selectable category.

11.2 Category Structure

The system supports parent and child categories.

Examples:

Vehicles

Cars

Motorcycles

Trucks

Real Estate

Apartments

Villas

Offices

Land

Electronics

Phones

Computers

Televisions

Services

Jobs

Furniture

Fashion

General Items

11.3 Category Information

Each category contains:

Arabic name

English name

Slug

Parent category

Icon

Optional cover image

Display order

Status

SEO title and description

Listing count

11.4 Category Statuses

Active

Hidden

Archived

Hidden categories cannot receive new listings.

Archived categories remain linked to historical listings.

11.5 Dynamic Attributes

Categories can define custom fields.

Cars:

Brand

Model

Year

Mileage

Fuel type

Transmission

Condition

Properties:

Property type

Bedrooms

Bathrooms

Area

Furnished

Parking

Jobs:

Employment type

Salary range

Experience

Education level

11.6 Attribute Types

Text

Long text

Number

Price

Dropdown

Radio

Checkbox

Boolean

Date

Multi-select

Each attribute may define:

Required status

Validation rules

Searchable status

Filterable status

Display order

Available options

Unit

Minimum and maximum values

11.7 Administration

Administrators can:

Create and edit categories

Reorder categories

Hide or archive categories

Manage translations

Manage attributes

Configure listing duration

Configure listing limits

Configure promotion availability

11.8 API Endpoints

GET /categories

GET /categories/tree

GET /categories/{id}

GET /categories/{id}/attributes

POST /admin/categories

PUT /admin/categories/{id}

DELETE /admin/categories/{id}

POST /admin/categories/{id}/attributes

PUT /admin/category-attributes/{id}

11.9 Acceptance Criteria

Category hierarchy works.

Arabic and English names display correctly.

Dynamic attributes load correctly.

Required attributes are validated.

Administrators can manage category configuration.

Categories with active listings cannot be permanently deleted.

12. Listings Module

12.1 Overview

Listings are the core marketplace content.

A listing may represent a product, vehicle, property, service, job, wanted request, event, or business offer.

12.2 Listing Lifecycle

Draft

Pending Review

Published

Rejected

Paused

Sold

Expired

Archived

Blocked

Deleted

12.3 Create Listing Requirements

The user must:

Have an active account

Verify the phone number

Accept marketplace terms

Remain within applicable listing limits

Required listing fields:

Title

Category

Description

Price type

Location

At least one image

Required category attributes

12.4 Title Rules

10–120 characters

No misleading language

No excessive symbols

No excessive capital letters

No prohibited contact details where disallowed

12.5 Description Rules

50–5,000 characters

Plain text

No scripts or unsafe HTML

Must accurately describe the offer

Must not contain prohibited content

12.6 Price Types

Fixed price

Negotiable

Free

Contact for price

Default currency: QAR.

12.7 Location

Country

City

Optional district

Optional map coordinates

Precise private location must not be publicly exposed without user consent.

12.8 Images

Minimum: 1

Maximum: 20

JPG, PNG, WEBP

Maximum: 10 MB each

First image is the cover

Images can be reordered

Images are compressed and resized

Thumbnails are generated

12.9 Video

Optional:

One video

MP4 or MOV

Maximum 60 seconds

Maximum 100 MB

Processed asynchronously

12.10 Edit Listing

The owner may update:

Title

Description

Price

Location

Media

Contact preferences

Dynamic attributes

Significant changes may return a published listing to moderation.

12.11 Pause and Reactivate

Paused listings:

Are hidden publicly

Cannot receive new enquiries

Remain visible to the owner

Can be reactivated before expiry

12.12 Mark as Sold

Sold listings:

Are removed from normal search results

May display a sold badge

Cannot receive new conversations

Preserve existing conversations

12.13 Expiration and Renewal

Default publication duration: 30 days.

Notifications:

Seven days before expiry

One day before expiry

At expiry

Renewal:

Extends the expiry date

Preserves content

May require moderation

May require payment

Must not create duplicates

12.14 Archive and Delete

Archived listings remain in user history.

Deleted listings use soft deletion where possible.

Payment, audit, and legally required records are preserved.

12.15 Featured Listings

Promotion options may include:

Featured badge

Top of category

Top of search

Homepage placement

Urgent badge

Highlight

Automatic bump

Eligibility:

Published

Not expired

Not blocked

Active owner account

Successful payment

Eligible category

12.16 Contact Preferences

Possible contact methods:

In-app messages

Phone

WhatsApp

Email

In-app messaging should be enabled by default.

Private contact information must follow user privacy settings.

12.17 Duplicate Detection

Signals:

Same owner

Similar title

Similar description

Matching images

Matching attributes

Same phone number

Same location

Possible duplicates may be rejected or sent for review.

12.18 Moderation

Moderation may include:

Required-field validation

Prohibited-word checks

Duplicate detection

Image checks

Fraud-risk scoring

Manual review

12.19 Rejection Reasons

Prohibited content

Wrong category

Misleading title

Insufficient description

Poor images

Duplicate listing

Unrealistic price

Counterfeit goods

Fraud risk

Copyright violation

Missing required documents

Local legal violation

Marketplace policy violation

Rejected listings may be corrected and resubmitted unless permanently blocked.

12.20 Prohibited Content

The platform must prohibit content that is illegal, dangerous, fraudulent, abusive, or otherwise disallowed by local law or platform policy.

The detailed prohibited-items policy must be reviewed before launch in Qatar.

12.21 Listing Limits

Limits may depend on:

Role

Verification level

Category

Account type

Subscription

Time period

Active listing count

All limits must be configurable.

12.22 Listing Statistics

Total views

Unique views

Favourite count

Message count

Phone reveal count

WhatsApp click count

Share count

Promotion impressions

Promotion clicks

12.23 SEO

Published listing pages include:

Unique title

Meta description

Canonical URL

Open Graph data

Structured data where applicable

Indexing rules based on status

12.24 Main API Endpoints

GET /listings

GET /listings/{id}

GET /listings/{id}/similar

POST /listings

PUT /listings/{id}

DELETE /listings/{id}

POST /listings/{id}/submit

POST /listings/{id}/pause

POST /listings/{id}/activate

POST /listings/{id}/mark-sold

POST /listings/{id}/renew

POST /listings/{id}/archive

POST /listings/{id}/restore

POST /listings/{id}/images

PUT /listings/{id}/images/reorder

DELETE /listings/{id}/images/{imageId}

POST /listings/{id}/video

GET /users/me/listings

GET /users/me/listings/{id}/statistics

12.25 Acceptance Criteria

Draft creation works.

Category fields are validated.

Media upload and reordering work.

Moderation states work.

Owners can manage listing lifecycle.

Expiration and renewal work.

Duplicate checks work.

Promotion eligibility works.

Privacy rules are respected.

Listing actions are logged.

Critical workflows are tested.

13. Search and Filters Module

13.1 Overview

Search helps users find relevant listings quickly through keywords, categories, locations, prices, and category-specific filters.

13.2 Search Inputs

Users can search using:

Keywords

Category

Location

Price range

Condition

Listing type

Category attributes

13.3 Search Behaviour

The system should:

Search titles and descriptions

Prioritise relevant title matches

Support Arabic and English

Handle common spelling variations where possible

Ignore unnecessary punctuation

Support partial matching

Exclude blocked, deleted, and inactive listings

13.4 Filters

Global filters:

Category

City

District

Minimum price

Maximum price

Price type

Condition

Date posted

Seller type

Verified seller

With images

Featured only

Dynamic filters come from category attributes.

13.5 Sorting

Most relevant

Newest

Oldest

Price low to high

Price high to low

Most viewed

Nearest, when location data is available

Paid promotion may affect placement but must not completely override relevance.

13.6 Search Suggestions

Suggestions may include:

Popular searches

Recent searches

Matching categories

Matching brands

Corrected spelling suggestions

13.7 Saved Searches

Authenticated users may save:

Search term

Category

Filters

Location

Notification preference

The system may notify users when new matching listings are published.

13.8 Search History

Users may view and clear personal search history.

Search history is private.

13.9 No-Results Experience

The system should:

Explain that no listings were found

Suggest removing filters

Suggest nearby categories

Display related listings

Allow saving the search

13.10 API Endpoints

GET /search

GET /search/suggestions

GET /search/popular

GET /users/me/searches

POST /users/me/searches

DELETE /users/me/searches/{id}

DELETE /users/me/search-history

13.11 Acceptance Criteria

Arabic and English search work.

Filters match category configuration.

Sorting works.

Inactive listings are excluded.

Search pagination works.

Saved searches work.

Search performance meets targets.

14. Favorites Module

14.1 Overview

Authenticated users can save listings for later.

14.2 Requirements

Users can:

Add a listing to favourites

Remove a listing from favourites

View all favourites

See favourite status on listing cards

Receive selected listing-change notifications

14.3 Business Rules

Guests are prompted to log in.

A listing can only be saved once per user.

Users cannot save deleted or blocked listings.

Deleted listings are removed from active favourite views.

Favourite counts must not expose user identities.

14.4 Optional Notifications

Users may receive notifications when:

Price changes

Listing is sold

Listing expires

Listing is removed

14.5 API Endpoints

GET /users/me/favorites

POST /listings/{id}/favorite

DELETE /listings/{id}/favorite

14.6 Acceptance Criteria

Add and remove actions work.

Duplicate favourites are prevented.

Favourite state is synchronised across pages.

Privacy is protected.

15. Messaging Module

15.1 Overview

Messaging allows buyers and sellers to communicate securely inside Tamam.

15.2 Conversation Creation

A conversation is normally linked to:

One listing

One buyer

One seller

A user cannot start a conversation with their own listing.

15.3 Message Types

MVP:

Text

Listing reference

System message

Optional:

Images

Location

Documents for approved categories

15.4 Conversation Features

Send and receive messages

Read status

Unread count

Conversation archive

Block user

Report conversation

Listing snapshot

Seller and buyer information

Timestamps

15.5 Safety

The system should detect or flag:

Spam

Repeated abusive messages

Suspicious links

Attempts to move users into scams

Prohibited language

Users can block another user.

Blocking prevents new messages while preserving records for moderation.

15.6 Listing Status Effects

When a listing is sold, expired, blocked, or deleted:

Existing conversations remain accessible.

New conversations may be disabled.

A system status message is shown.

15.7 Notifications

Users receive real-time or near-real-time notifications for new messages.

Email or push notifications depend on preferences.

15.8 API Endpoints

GET /conversations

POST /conversations

GET /conversations/{id}

POST /conversations/{id}/messages

POST /conversations/{id}/read

POST /conversations/{id}/archive

POST /conversations/{id}/block

POST /conversations/{id}/report

15.9 Acceptance Criteria

Eligible users can start conversations.

Duplicate listing conversations are handled consistently.

Messages appear in correct order.

Read and unread states work.

Blocking and reporting work.

Private messages are only visible to authorised participants and moderators with permission.

16. Notifications Module

16.1 Overview

Notifications inform users about important marketplace activity.

16.2 Notification Channels

MVP:

In-app

Email

Future:

Push

SMS

WhatsApp, where permitted

16.3 Notification Types

New message

Listing approved

Listing rejected

Listing expiring

Listing expired

Listing reported

Price-change alert

Saved-search match

New review

Payment successful

Payment failed

Subscription renewal

Security alert

Administrative announcement

16.4 Notification Centre

Users can:

View notifications

Mark one as read

Mark all as read

Delete eligible notifications

Open the related page

16.5 Preferences

Users may control channels by notification category.

Critical security and account messages cannot be disabled.

16.6 API Endpoints

GET /notifications

POST /notifications/{id}/read

POST /notifications/read-all

DELETE /notifications/{id}

GET /users/me/notification-preferences

PUT /users/me/notification-preferences

16.7 Acceptance Criteria

Notifications are created for defined events.

Read states work.

Preferences are respected.

Critical notifications cannot be disabled.

Sensitive data is not included in unsafe channels.

17. Reviews and Ratings Module

17.1 Overview

Reviews help establish trust between users.

17.2 Eligibility

A review may be submitted when:

Users have an eligible marketplace interaction

The listing or transaction state permits reviewing

The reviewer is not reviewing their own account

The reviewer has not already reviewed the same interaction

17.3 Review Content

Rating: 1–5

Optional written review

Optional predefined tags

Related listing or interaction

Date submitted

17.4 Review Rules

No abusive content

No private personal information

No threats

No irrelevant advertising

No duplicate reviews

No paid or manipulated reviews

17.5 Rating Calculation

The public rating should include:

Average rating

Review count

Optional rating distribution

Removed fraudulent reviews do not count.

17.6 Review Moderation

Users can report reviews.

Administrators can:

Hide reviews

Remove reviews

Restore reviews

Record moderation reasons

17.7 API Endpoints

GET /users/{id}/reviews

POST /reviews

PUT /reviews/{id}

DELETE /reviews/{id}

POST /reviews/{id}/report

GET /admin/reviews

17.8 Acceptance Criteria

Only eligible users can review.

Duplicate reviews are prevented.

Rating calculations are accurate.

Report and moderation workflows work.

Review privacy and content policies are enforced.

18. Reports and Moderation Module

18.1 Overview

Users can report listings, users, reviews, messages, or businesses.

Moderators investigate and apply appropriate actions.

18.2 Report Types

Scam or fraud

Prohibited item

Counterfeit item

Duplicate listing

Incorrect category

Misleading information

Abusive content

Harassment

Spam

Privacy violation

Copyright complaint

Other

18.3 Report Information

Reporter

Reported entity type

Reported entity ID

Reason

Description

Evidence

Status

Assigned moderator

Resolution

Internal notes

Timestamps

18.4 Report Statuses

New

Under review

Awaiting information

Resolved

Rejected

Escalated

18.5 Moderation Actions

No action

Warning

Content edit request

Listing rejection

Listing block

Review removal

Message restriction

Temporary suspension

Permanent account block

Escalation

18.6 Appeals

Users may appeal eligible moderation decisions.

Appeals must be reviewed by an authorised moderator who did not make the original decision where operationally possible.

18.7 Audit Requirements

Every moderation action records:

Actor

Action

Target

Reason

Previous state

New state

Timestamp

Internal note

18.8 API Endpoints

POST /reports

GET /users/me/reports

GET /admin/reports

GET /admin/reports/{id}

POST /admin/reports/{id}/assign

POST /admin/reports/{id}/resolve

POST /admin/reports/{id}/escalate

POST /moderation/appeals

18.9 Acceptance Criteria

Users can report eligible entities.

Moderators can investigate reports.

Actions are permission-controlled.

Audit logs are complete.

Appeals work for eligible decisions.

Reporter identity is protected where appropriate.

19. Business Accounts Module

19.1 Overview

Business accounts provide professional marketplace tools for verified organisations.

19.2 Registration

Business information:

Legal or trading name

Business category

Commercial registration details where required

Contact person

Phone

Email

Website

Address

Logo

Description

Verification documents

19.3 Verification Statuses

Draft

Pending verification

Verified

Rejected

Suspended

19.4 Public Business Profile

Logo

Business name

Verified badge

Description

Categories

Location

Contact methods

Opening hours

Active listings

Reviews

Website where permitted

19.5 Business Features

Higher listing limits

Business dashboard

Listing analytics

Subscription packages

Promotion discounts

Business verification badge

Bulk listing tools in future

Team members in future

19.6 Business Rules

Verification is required for the verified badge.

Documents are private.

Business names must not impersonate another organisation.

Suspended businesses cannot publish.

Business accounts remain subject to all marketplace rules.

19.7 API Endpoints

POST /businesses

GET /businesses/{id}

PUT /businesses/{id}

POST /businesses/{id}/documents

POST /businesses/{id}/submit-verification

GET /businesses/{id}/listings

GET /businesses/{id}/analytics

GET /admin/businesses

POST /admin/businesses/{id}/verify

POST /admin/businesses/{id}/reject

POST /admin/businesses/{id}/suspend

19.8 Acceptance Criteria

Businesses can apply.

Documents remain private.

Administrators can verify businesses.

Verified badges are accurate.

Business limits and features work.

Suspended businesses lose publishing access.

20. Premium Features Module

20.1 Overview

Premium features increase listing visibility and provide enhanced tools.

20.2 Promotion Products

Possible products:

Featured badge

Top of category

Top of search

Homepage placement

Urgent badge

Listing highlight

Automatic bump

Listing renewal package

20.3 Subscription Features

Possible subscription benefits:

Higher listing limits

Monthly promotion credits

Business analytics

Priority support

Business profile tools

Discounted promotions

Longer listing duration

20.4 Eligibility

Premium features require:

Active account

Eligible listing

Eligible category

Successful payment

Compliance with marketplace policy

20.5 Promotion Scheduling

A promotion records:

Listing

Product

Start date

End date

Status

Payment

Placement

Impressions

Clicks

20.6 Statuses

Pending payment

Scheduled

Active

Paused

Completed

Cancelled

Refunded

20.7 Acceptance Criteria

Eligible promotions can be purchased.

Promotion duration is enforced.

Ineligible listings cannot be promoted.

Promotion metrics are recorded.

Expired promotions stop automatically.

Refund or cancellation status is recorded.

21. Payments Module

21.1 Overview

The Payments module records and processes payments for promotions, packages, and subscriptions.

21.2 Supported Purchases

Listing promotion

Subscription

Listing package

Renewal package

Business package

21.3 Payment Statuses

Pending

Processing

Successful

Failed

Cancelled

Refunded

Partially refunded

21.4 Payment Requirements

Each payment record includes:

User or business

Order

Product

Amount

Currency

Provider

Provider reference

Status

Timestamps

Invoice or receipt reference

21.5 Security

Card details must not be stored by Tamam.

Payment provider callbacks must be verified.

Duplicate callbacks must be safely handled.

Successful payment must be confirmed server-side.

Financial actions must be logged.

21.6 Refunds

Refund eligibility depends on:

Product

Promotion state

Moderation status

Payment provider

Marketplace policy

Only authorised administrators can approve manual refunds.

21.7 Subscriptions

Subscriptions include:

Package

Start date

Renewal date

Status

Auto-renew preference

Included benefits

Usage limits

21.8 API Endpoints

GET /payment-products

POST /payments/checkout

POST /payments/webhook

GET /payments/{id}

GET /users/me/payments

GET /users/me/subscriptions

POST /subscriptions/{id}/cancel

POST /admin/payments/{id}/refund

21.9 Acceptance Criteria

Checkout creates a valid pending payment.

Provider confirmation updates status.

Duplicate webhook events are safe.

Promotions activate only after successful payment.

Failed payments do not activate benefits.

Receipts and records are preserved.

Refund permissions are enforced.

22. Admin Dashboard Module

22.1 Overview

The administration dashboard provides secure tools for platform operations.

22.2 Dashboard Areas

Overview

Users

Businesses

Listings

Categories

Moderation

Reports

Reviews

Payments

Subscriptions

Promotions

Notifications

Content pages

Analytics

System settings

Roles and permissions

Audit logs

22.3 User Management

Administrators can:

Search users

View account details

View verification state

Suspend or block accounts

Restore eligible accounts

Review account history

Add internal notes

22.4 Listing Management

Administrators can:

Search and filter listings

Review pending listings

Approve or reject

Block or restore

Correct categories

View reports and history

Inspect promotion status

22.5 Category Management

Create and edit

Translate names

Reorder

Configure attributes

Configure limits

Configure durations

Configure promotion eligibility

22.6 Platform Settings

Examples:

Default listing duration

Maximum images

Upload limits

Verification rules

Listing limits

Promotion pricing

Supported locations

Notification templates

Maintenance mode

22.7 Roles and Permissions

Permissions must be granular.

Examples:

View users

Suspend users

Review listings

Manage categories

View payments

Approve refunds

Manage administrators

View audit logs

22.8 Acceptance Criteria

Access is permission-based.

Critical actions require confirmation.

Administrative actions are logged.

Search and filters work.

Moderation queues are usable.

Sensitive payment and identity data are restricted.

23. Analytics and Reporting Module

23.1 Overview

Analytics help administrators and businesses understand marketplace performance.

23.2 Platform Metrics

Registered users

Active users

New users

Verified users

Business accounts

Published listings

Pending listings

Rejected listings

Sold listings

Searches

Conversations

Reports

Revenue

Promotion purchases

Subscription conversion

23.3 Listing Metrics

Views

Unique views

Favourites

Messages

Contact actions

Shares

Promotion impressions

Promotion clicks

Conversion indicators

23.4 Business Analytics

Business accounts may view:

Listing performance

Active inventory

Views

Leads

Favourite count

Promotion performance

Subscription usage

23.5 Date Filters

Today

Yesterday

Last 7 days

Last 30 days

Custom range

23.6 Export

Authorised administrators may export selected reports in:

CSV

XLSX in future

PDF in future

Exports must follow permissions and privacy rules.

23.7 Acceptance Criteria

Metrics use consistent definitions.

Date filters work.

Business users only see their own data.

Administrator access follows permissions.

Analytics do not expose private user information.

Reports can be exported where enabled.

24. Future Features

Possible future additions:

Native iOS and Android apps

AI listing assistant

AI moderation

Personalised recommendations

Image-based search

Voice search

Integrated delivery

Escrow

Marketplace wallet

Auctions

Offers and counteroffers

Loyalty programme

Seller levels

Team accounts

Bulk upload

Inventory integration

CRM integration

Multi-country GCC support

Multi-currency support

Additional languages

Vehicle history integrations

Property map search

Appointment booking

Service booking

Advanced fraud detection

Future features must not be implemented in the MVP unless separately approved.

25. Global Acceptance Criteria

The MVP is ready for controlled launch when:

Arabic and English interfaces work.

Registration, login, and verification work.

Users can manage profiles.

Categories and dynamic attributes work.

Users can create and manage listings.

Moderation workflows work.

Public browsing and search work.

Favourites work.

Messaging works.

Notifications work.

Reviews and reports work.

Business applications work.

Promotions and payment records work.

Administrators can manage the marketplace.

Permissions are enforced.

Audit logs are recorded.

Private data is protected.

Critical workflows have automated tests.

Major pages are responsive.

Loading, empty, error, and success states are implemented.

Backups, monitoring, and production configuration are prepared.

Qatar-specific marketplace policies have been legally and operationally reviewed.

End of Document
