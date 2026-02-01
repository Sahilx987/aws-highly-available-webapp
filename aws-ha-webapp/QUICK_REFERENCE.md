# AWS Highly Available Web App - Quick Reference

## 📋 Architecture at a Glance

```
Internet → IGW → ALB (Public) → EC2 (Private) → RDS (Private)
                                     ↓
                                 NAT Gateway → Internet
```

---

## 🚀 Quick Deploy Commands

### 1. Create VPC and Networking
```bash
# Create VPC
aws ec2 create-vpc --cidr-block 10.20.0.0/16

# Create subnets (repeat for each)
aws ec2 create-subnet --vpc-id vpc-xxx --cidr-block 10.20.1.0/24 --availability-zone eu-west-3a

# Create and attach Internet Gateway
aws ec2 create-internet-gateway
aws ec2 attach-internet-gateway --vpc-id vpc-xxx --internet-gateway-id igw-xxx
```

### 2. Create Security Groups
```bash
# ALB Security Group
aws ec2 create-security-group --group-name SG-ALB --description "ALB" --vpc-id vpc-xxx
aws ec2 authorize-security-group-ingress --group-id sg-xxx --protocol tcp --port 80 --cidr 0.0.0.0/0

# EC2 Security Group  
aws ec2 create-security-group --group-name SG-EC2 --description "EC2" --vpc-id vpc-xxx
aws ec2 authorize-security-group-ingress --group-id sg-xxx --protocol tcp --port 80 --source-group sg-ALB

# RDS Security Group
aws ec2 create-security-group --group-name SG-RDS --description "RDS" --vpc-id vpc-xxx
aws ec2 authorize-security-group-ingress --group-id sg-xxx --protocol tcp --port 3306 --source-group sg-EC2
```

### 3. Create RDS Database
```bash
# Create DB Subnet Group
aws rds create-db-subnet-group \
    --db-subnet-group-name webapp-db-subnet-group \
    --subnet-ids subnet-xxx subnet-yyy

# Create RDS Instance
aws rds create-db-instance \
    --db-instance-identifier webapp-db \
    --db-instance-class db.t3.micro \
    --engine mysql \
    --master-username admin \
    --master-user-password YourPassword123! \
    --allocated-storage 20 \
    --multi-az
```

### 4. Initialize Database
```bash
mysql -h rds-endpoint.amazonaws.com -u admin -p < database/schema.sql
```

### 5. Create Load Balancer
```bash
# Create ALB
aws elbv2 create-load-balancer \
    --name webapp-alb \
    --subnets subnet-xxx subnet-yyy \
    --security-groups sg-ALB

# Create Target Group
aws elbv2 create-target-group \
    --name webapp-tg \
    --protocol HTTP \
    --port 80 \
    --vpc-id vpc-xxx \
    --health-check-path /health.php
```

### 6. Create Launch Template
```bash
# Edit user-data.sh first (add RDS endpoint and password)
aws ec2 create-launch-template \
    --launch-template-name webapp-template \
    --launch-template-data file://template.json
```

### 7. Create Auto Scaling Group
```bash
aws autoscaling create-auto-scaling-group \
    --auto-scaling-group-name webapp-asg \
    --launch-template LaunchTemplateName=webapp-template \
    --min-size 2 \
    --max-size 6 \
    --desired-capacity 2 \
    --vpc-zone-identifier "subnet-xxx,subnet-yyy" \
    --target-group-arns arn:aws:elasticloadbalancing:... \
    --health-check-type ELB
```

---

## 🔍 Quick Troubleshooting

### Check Instance Health
```bash
aws elbv2 describe-target-health --target-group-arn arn:...
```

### View Logs
```bash
# User data log
sudo cat /var/log/cloud-init-output.log

# Apache error log
sudo tail -f /var/log/apache2/error.log

# PHP errors
sudo tail -f /var/log/apache2/error.log | grep PHP
```

### Test Database Connection
```bash
# From EC2
mysql -h rds-endpoint -u admin -p

# From PHP
php -r '$c = new mysqli(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASS"), getenv("DB_NAME")); echo $c->connect_error;'
```

### Test Health Endpoint
```bash
curl http://localhost/health.php
```

### Check Environment Variables
```bash
# In PHP
php -r 'var_dump(getenv("DB_HOST"));'

# From Apache config
grep SetEnv /etc/apache2/sites-available/000-default.conf
```

---

## 📊 Monitoring Commands

### CloudWatch Metrics
```bash
# ALB 5xx errors
aws cloudwatch get-metric-statistics \
    --namespace AWS/ApplicationELB \
    --metric-name HTTPCode_Target_5XX_Count \
    --dimensions Name=LoadBalancer,Value=app/webapp-alb/xxx \
    --start-time 2024-01-01T00:00:00Z \
    --end-time 2024-01-01T23:59:59Z \
    --period 300 \
    --statistics Sum

# RDS connections
aws cloudwatch get-metric-statistics \
    --namespace AWS/RDS \
    --metric-name DatabaseConnections \
    --dimensions Name=DBInstanceIdentifier,Value=webapp-db \
    --start-time 2024-01-01T00:00:00Z \
    --end-time 2024-01-01T23:59:59Z \
    --period 300 \
    --statistics Average
```

---

## 🧪 Testing Commands

### Load Balancing Test
```bash
for i in {1..20}; do 
    curl -s http://your-alb.amazonaws.com | grep -o 'ip-10-20-[0-9-]*'
done | sort | uniq -c
```

### Database Insert Test
```bash
mysql -h rds-endpoint -u admin -p webapp_db <<EOF
INSERT INTO submissions (name, email, message) 
VALUES ('Test', 'test@example.com', 'Testing from CLI');
SELECT * FROM submissions ORDER BY created_at DESC LIMIT 5;
EOF
```

### Simulate High CPU (Trigger Scaling)
```bash
sudo apt-get install stress
stress --cpu 4 --timeout 600s
```

### Watch Auto Scaling Activity
```bash
watch -n 5 'aws autoscaling describe-auto-scaling-groups \
    --auto-scaling-group-names webapp-asg \
    --query "AutoScalingGroups[0].Instances[].[InstanceId,LifecycleState]"'
```

---

## 🔄 Update & Refresh Commands

### Update Launch Template
```bash
# Create new version
aws ec2 create-launch-template-version \
    --launch-template-name webapp-template \
    --source-version 1 \
    --launch-template-data file://updated-template.json

# Set as default
aws ec2 modify-launch-template \
    --launch-template-name webapp-template \
    --default-version 2
```

### Instance Refresh
```bash
aws autoscaling start-instance-refresh \
    --auto-scaling-group-name webapp-asg \
    --preferences MinHealthyPercentage=90
```

### Manual Instance Replacement
```bash
# Terminate instance
aws ec2 terminate-instances --instance-ids i-xxx

# Auto Scaling automatically launches replacement
```

---

## 📈 Scaling Commands

### Manual Scaling
```bash
# Scale up
aws autoscaling set-desired-capacity \
    --auto-scaling-group-name webapp-asg \
    --desired-capacity 4

# Scale down
aws autoscaling set-desired-capacity \
    --auto-scaling-group-name webapp-asg \
    --desired-capacity 2
```

### Update Scaling Policies
```bash
aws autoscaling put-scaling-policy \
    --auto-scaling-group-name webapp-asg \
    --policy-name cpu-tracking \
    --policy-type TargetTrackingScaling \
    --target-tracking-configuration '{
        "PredefinedMetricSpecification": {
            "PredefinedMetricType": "ASGAverageCPUUtilization"
        },
        "TargetValue": 70.0
    }'
```

---

## 🧹 Cleanup Commands (When Done)

```bash
# Delete in reverse order

# 1. Delete Auto Scaling Group
aws autoscaling delete-auto-scaling-group \
    --auto-scaling-group-name webapp-asg \
    --force-delete

# 2. Delete Launch Template
aws ec2 delete-launch-template --launch-template-name webapp-template

# 3. Delete ALB
aws elbv2 delete-load-balancer --load-balancer-arn arn:...
aws elbv2 delete-target-group --target-group-arn arn:...

# 4. Delete RDS
aws rds delete-db-instance \
    --db-instance-identifier webapp-db \
    --skip-final-snapshot

# 5. Delete NAT Gateways
aws ec2 delete-nat-gateway --nat-gateway-id nat-xxx

# 6. Release Elastic IPs
aws ec2 release-address --allocation-id eipalloc-xxx

# 7. Delete Internet Gateway
aws ec2 detach-internet-gateway --internet-gateway-id igw-xxx --vpc-id vpc-xxx
aws ec2 delete-internet-gateway --internet-gateway-id igw-xxx

# 8. Delete Subnets
aws ec2 delete-subnet --subnet-id subnet-xxx

# 9. Delete Security Groups
aws ec2 delete-security-group --group-id sg-xxx

# 10. Delete VPC
aws ec2 delete-vpc --vpc-id vpc-xxx
```

---

## 💡 Common Issues Quick Fix

| Issue | Quick Fix |
|-------|-----------|
| 502 from ALB | Check target health: `aws elbv2 describe-target-health` |
| Can't connect to RDS | Check security group allows 3306 from EC2 |
| Instances unhealthy | Check Apache running: `systemctl status apache2` |
| User data failed | Check logs: `cat /var/log/cloud-init-output.log` |
| Env vars not working | Verify SetEnv in Apache config |
| No internet in private subnet | Check NAT Gateway route in route table |

---

## 📞 Get Help

- **AWS Documentation**: https://docs.aws.amazon.com
- **GitHub Issues**: [Your repo URL]/issues
- **Stack Overflow**: Tag `amazon-web-services`

---

## 🎯 Cost Estimate

| Service | Monthly Cost |
|---------|-------------|
| ALB | $22.50 |
| NAT Gateway (2 AZs) | $64.80 |
| EC2 (2x t3.micro) | $15.00 |
| RDS (db.t3.micro Multi-AZ) | $29.00 |
| EBS Storage | $8.00 |
| Data Transfer | $9.00 |
| **Total** | **~$148** |

**Optimization**: Use Reserved Instances to save 40-60%

---

**Version:** 1.0  
**Last Updated:** January 2026  
**Author:** [Your Name]
