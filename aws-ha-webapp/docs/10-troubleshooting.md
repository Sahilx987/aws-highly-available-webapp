# Troubleshooting Guide

This guide covers common issues and their solutions when deploying the Highly Available Web Application on AWS.

## Table of Contents
1. [EC2 Instance Issues](#ec2-instance-issues)
2. [Load Balancer Issues](#load-balancer-issues)
3. [Database Connection Issues](#database-connection-issues)
4. [Auto Scaling Issues](#auto-scaling-issues)
5. [Application Errors](#application-errors)
6. [Network Connectivity Issues](#network-connectivity-issues)

---

## EC2 Instance Issues

### Issue 1: Instances Showing "Unhealthy" in Target Group

**Symptoms:**
- Instances register with target group but show "unhealthy" status
- ALB returns 502/503 errors

**Diagnosis:**
```bash
# Check instance system status
aws ec2 describe-instance-status --instance-ids i-xxxxx

# Check target health
aws elbv2 describe-target-health \
    --target-group-arn arn:aws:elasticloadbalancing:...
```

**Common Causes:**

1. **Apache not running**
   ```bash
   # SSH into instance
   sudo systemctl status apache2
   
   # If not running, start it
   sudo systemctl start apache2
   sudo systemctl enable apache2
   ```

2. **Health check path doesn't exist**
   ```bash
   # Test locally
   curl http://localhost/health.php
   
   # Check if file exists
   ls -la /var/www/html/health.php
   ```

3. **Security group blocks ALB traffic**
   ```bash
   # Verify security group allows HTTP from ALB
   # SG-EC2 should allow port 80 from SG-ALB
   aws ec2 describe-security-groups --group-ids sg-xxxxx
   ```

**Solution:**
```bash
# Fix security group (if needed)
aws ec2 authorize-security-group-ingress \
    --group-id sg-EC2 \
    --protocol tcp \
    --port 80 \
    --source-group sg-ALB
```

---

### Issue 2: User Data Script Failed

**Symptoms:**
- Instance launches but application not installed
- Seeing Apache default page instead of custom app

**Diagnosis:**
```bash
# View user data execution log
sudo cat /var/log/cloud-init-output.log

# Check for errors
sudo grep -i error /var/log/cloud-init-output.log
```

**Common Causes:**

1. **Database credentials not set**
   - User data contains placeholder values
   - Solution: Edit launch template, replace placeholders

2. **Network connectivity issues during boot**
   - apt-get update failed
   - Solution: Add retry logic to user data

3. **Syntax errors in user data script**
   ```bash
   # Test script locally
   bash -n /path/to/user-data.sh
   ```

**Solution:**
```bash
# Manually run user data to debug
sudo bash /var/lib/cloud/instance/user-data.txt

# Fix and update launch template
aws ec2 create-launch-template-version \
    --launch-template-name webapp-launch-template \
    --source-version 1 \
    --version-description "Fixed user data" \
    --launch-template-data file://updated-template.json
```

---

## Load Balancer Issues

### Issue 3: ALB Returns 502 Bad Gateway

**Symptoms:**
- Intermittent 502 errors
- Some requests succeed, some fail

**Diagnosis:**
```bash
# Check target health
aws elbv2 describe-target-health --target-group-arn arn:...

# Check ALB metrics
aws cloudwatch get-metric-statistics \
    --namespace AWS/ApplicationELB \
    --metric-name HTTPCode_Target_5XX_Count \
    --dimensions Name=LoadBalancer,Value=app/webapp-alb/xxx \
    --start-time 2024-01-01T00:00:00Z \
    --end-time 2024-01-01T23:59:59Z \
    --period 300 \
    --statistics Sum
```

**Common Causes:**

1. **Mix of healthy and unhealthy instances**
   - ALB routes to unhealthy instance before health check marks it down
   - Solution: Increase health check frequency

2. **Instance capacity exceeded**
   - CPU or memory exhausted
   - Solution: Scale out or increase instance size

**Solution:**
```bash
# Update health check settings
aws elbv2 modify-target-group \
    --target-group-arn arn:... \
    --health-check-interval-seconds 15 \
    --healthy-threshold-count 2 \
    --unhealthy-threshold-count 2
```

---

## Database Connection Issues

### Issue 4: "Access Denied" Error

**Symptoms:**
- Application shows database connection failed
- Error: "Access denied for user 'admin'@'10.20.11.x'"

**Diagnosis:**
```bash
# Test connection from EC2
mysql -h rds-endpoint.amazonaws.com -u admin -p

# Check RDS logs
aws rds describe-db-log-files --db-instance-identifier webapp-db
```

**Common Causes:**

1. **Wrong password**
   - Password mismatch in user data vs RDS
   - Solution: Verify password in Secrets Manager or user data

2. **User doesn't exist**
   - Using wrong username
   - Solution: Check RDS master username

**Solution:**
```bash
# Reset RDS master password
aws rds modify-db-instance \
    --db-instance-identifier webapp-db \
    --master-user-password NewSecurePassword123! \
    --apply-immediately
```

---

### Issue 5: "Can't Connect to MySQL Server"

**Symptoms:**
- Connection timeout
- Error: "(HY000/2002): Can't connect to MySQL server"

**Diagnosis:**
```bash
# Test network connectivity
telnet rds-endpoint.amazonaws.com 3306

# Check security groups
aws ec2 describe-security-groups --group-ids sg-RDS
```

**Common Causes:**

1. **Security group doesn't allow EC2 → RDS**
   ```bash
   # Check if SG-RDS allows port 3306 from SG-EC2
   aws ec2 describe-security-groups \
       --group-ids sg-RDS \
       --query 'SecurityGroups[0].IpPermissions'
   ```

2. **Wrong RDS endpoint**
   - Using cluster endpoint instead of instance endpoint
   - Solution: Verify endpoint in user data

3. **RDS in different VPC**
   - RDS and EC2 not in same VPC
   - Solution: Check VPC IDs match

**Solution:**
```bash
# Add security group rule
aws ec2 authorize-security-group-ingress \
    --group-id sg-RDS \
    --protocol tcp \
    --port 3306 \
    --source-group sg-EC2
```

---

## Auto Scaling Issues

### Issue 6: Auto Scaling Not Launching Instances

**Symptoms:**
- Desired capacity increases but no new instances launch
- CloudWatch shows scaling activity but no instances

**Diagnosis:**
```bash
# Check Auto Scaling activities
aws autoscaling describe-scaling-activities \
    --auto-scaling-group-name webapp-asg \
    --max-records 10

# Check for errors
aws autoscaling describe-scaling-activities \
    --auto-scaling-group-name webapp-asg \
    --query 'Activities[?StatusCode==`Failed`]'
```

**Common Causes:**

1. **Insufficient capacity in AZ**
   - AWS doesn't have enough capacity
   - Solution: Try different instance type or AZ

2. **Launch template error**
   - Invalid AMI ID or security group
   - Solution: Validate launch template

3. **Service quota exceeded**
   - Hit EC2 instance limit
   - Solution: Request quota increase

**Solution:**
```bash
# Check service quotas
aws service-quotas get-service-quota \
    --service-code ec2 \
    --quota-code L-1216C47A

# Request quota increase if needed
aws service-quotas request-service-quota-increase \
    --service-code ec2 \
    --quota-code L-1216C47A \
    --desired-value 20
```

---

## Application Errors

### Issue 7: HTTP 500 Internal Server Error

**Symptoms:**
- Form submission returns 500 error
- Intermittent failures

**Diagnosis:**
```bash
# Check PHP error logs
sudo tail -f /var/log/apache2/error.log

# Enable PHP error display (dev only)
sudo nano /etc/php/8.1/apache2/php.ini
# Set: display_errors = On
sudo systemctl restart apache2
```

**Common Causes:**

1. **Environment variables not set**
   ```bash
   # Test if PHP can see env vars
   php -r 'var_dump(getenv("DB_HOST"));'
   
   # If returns false, Apache config issue
   ```

2. **Database connection failed**
   ```bash
   # Test database connection in PHP
   php -r '
   $c = new mysqli(
       getenv("DB_HOST"), 
       getenv("DB_USER"), 
       getenv("DB_PASS"), 
       getenv("DB_NAME")
   ); 
   echo $c->connect_error;
   '
   ```

**Solution:**
```bash
# Fix Apache environment variables
sudo nano /etc/apache2/sites-available/000-default.conf

# Add SetEnv directives
SetEnv DB_HOST "rds-endpoint.amazonaws.com"
SetEnv DB_USER "admin"
SetEnv DB_PASS "password"
SetEnv DB_NAME "webapp_db"

sudo systemctl restart apache2
```

---

### Issue 8: "Table Doesn't Exist" Error

**Symptoms:**
- Error: "Table 'webapp_db.submissions' doesn't exist"
- Application loads but can't display submissions

**Diagnosis:**
```bash
# Connect to RDS
mysql -h rds-endpoint.amazonaws.com -u admin -p

# Check if table exists
USE webapp_db;
SHOW TABLES;
DESCRIBE submissions;
```

**Solution:**
```bash
# Create table (run schema.sql)
mysql -h rds-endpoint.amazonaws.com -u admin -p webapp_db < database/schema.sql
```

---

## Network Connectivity Issues

### Issue 9: Instances Can't Reach Internet

**Symptoms:**
- apt-get update fails
- Can't download packages
- User data script fails

**Diagnosis:**
```bash
# Test internet connectivity
ping 8.8.8.8
curl https://google.com

# Check route table
aws ec2 describe-route-tables --filters "Name=association.subnet-id,Values=subnet-xxxxx"
```

**Common Causes:**

1. **No NAT Gateway**
   - Private subnet route table missing NAT Gateway
   - Solution: Add NAT Gateway route

2. **NAT Gateway in wrong subnet**
   - NAT Gateway must be in public subnet
   - Solution: Move NAT Gateway

**Solution:**
```bash
# Create route to NAT Gateway
aws ec2 create-route \
    --route-table-id rtb-xxxxx \
    --destination-cidr-block 0.0.0.0/0 \
    --nat-gateway-id nat-xxxxx
```

---

## General Debugging Commands

### Essential Commands

```bash
# Check all resources in VPC
aws ec2 describe-vpcs --vpc-ids vpc-xxxxx

# List all instances
aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=webapp-asg-instance" \
    --query 'Reservations[].Instances[].[InstanceId,State.Name,PrivateIpAddress]'

# Check CloudWatch logs
aws logs tail /aws/ec2/webapp --follow

# View RDS status
aws rds describe-db-instances \
    --db-instance-identifier webapp-db \
    --query 'DBInstances[0].[DBInstanceStatus,Endpoint.Address,MultiAZ]'

# Test ALB DNS resolution
nslookup webapp-alb-xxxxx.eu-west-3.elb.amazonaws.com

# Test database from EC2
mysql -h rds-endpoint -u admin -p -e "SELECT 1;"
```

---

## Getting Help

If you're still stuck after trying these solutions:

1. **Check AWS Service Health Dashboard**: https://status.aws.amazon.com/
2. **Review CloudWatch Logs**: All application and system logs
3. **Open GitHub Issue**: [Link to your repository issues]
4. **AWS Support**: If you have a support plan
5. **Stack Overflow**: Tag with `amazon-web-services`, `aws-ec2`, `aws-rds`

---

## Prevention Checklist

Before deploying, verify:

- [ ] All placeholder values replaced in user-data.sh
- [ ] Security groups properly configured (ALB → EC2 → RDS)
- [ ] RDS endpoint correct in user data
- [ ] Database schema created
- [ ] Health check path (/health.php) returns 200
- [ ] NAT Gateway route in private subnet route table
- [ ] Launch template user data tested on single instance
- [ ] CloudWatch monitoring enabled
- [ ] Backup retention configured on RDS

---

**Last Updated:** January 2026
