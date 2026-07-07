# PitCrew Connect – AWS Cloud Deployment

PitCrew Connect is a car maintenance and service booking platform for everyday vehicle owners, deployed on AWS as part of the CNCO3003 Cloud Computing assignment (Group project). The project covers both the business case for a cloud-first design and a hands-on AWS Academy Lab implementation of the full infrastructure.

## Project Description

PitCrew Connect lets customers book vehicle services (oil changes, tyre checks, brake checks, battery checks, pre-trip safety checks, and general maintenance), track booking status and ETA, and message the service team directly. On the staff side, a unified dashboard supports assigning employees, updating booking status, and managing service content — inspired by the coordination and clarity of a motorsport pit crew.

This repository documents the design and cloud deployment of that platform: the business model and target market, the AWS architecture chosen over in-house/hybrid alternatives, cost estimation and risk controls, and the step-by-step AWS implementation with supporting screenshots.

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP |
| Database | Amazon RDS (MySQL) |
| Web server | Apache on Amazon Linux (EC2) |
| Cloud platform | AWS (VPC, EC2, RDS, EBS, CloudWatch, ALB, AMI, Launch Template, Auto Scaling) |

## Architecture

Public traffic hits an Application Load Balancer, which routes requests to EC2 web server instances managed by an Auto Scaling Group (built from a custom AMI and launch template). The application stores data in a private Amazon RDS MySQL instance, isolated inside a custom VPC. CloudWatch monitors EC2/RDS performance, and EBS volumes with snapshots provide backup storage — keeping the database and internal network isolated from direct public access.

## AWS Implementation Tasks

1. **IAM** – Lab-controlled role and access management
2. **VPC & EC2 Web Server Setup** – Custom network, subnets, routing, and the initial web server
3. **Web Feature Deployment** – Deploying the PHP/Apache application
4. **Instance Scaling & Monitoring** – CloudWatch metrics and performance visibility
5. **Storage Expansion & Backup** – EBS volume expansion, snapshots, and AMI creation
6. **Database Integration (Amazon RDS)** – MySQL database setup and private connectivity
7. **Auto Scaling & Load Balancing** – Launch template, Auto Scaling Group, and Application Load Balancer
8. **Video Presentation** – Walkthrough demo of the deployed system

## Cost Estimate

Estimated using the AWS Pricing Calculator (US East – N. Virginia): **$42.06/month** (~$504.72/year), covering a t2.micro EC2 instance, a db.t2.micro Single-AZ RDS MySQL instance, one Application Load Balancer, EBS backup storage, and low-volume data transfer — within the project's $50/month budget target.

## Known Limitations (Prototype Scope)

This is an academic prototype, not a production deployment. It currently lacks HTTPS, a custom domain, secrets management, and MFA/finer-grained staff access control.

## Authors

Group 17 – CNCO3003 Cloud Computing
