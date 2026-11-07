variable "IMAGE_REPOSITORY" { default = "fossbilling-circleci-integration" }
variable "RELEASE" { default = "local" }
group "default" { targets = ["apache", "browser"] }
target "common" {
  context = "."
  dockerfile = ".circleci/images/Dockerfile"
  platforms = ["linux/amd64"]
  output = ["type=docker"]
}
target "apache" {
  inherits = ["common"]
  target = "apache"
  tags = ["${IMAGE_REPOSITORY}:${RELEASE}-apache"]
}
target "browser" {
  inherits = ["common"]
  target = "browser"
  tags = ["${IMAGE_REPOSITORY}:${RELEASE}-browser"]
}
